import marimo

__generated_with = "0.25.2"
app = marimo.App(width="medium", app_title="Bestandsbildnerinnen im DLA")


@app.cell(hide_code=True)
def _(mo):
    mo.md(r"""
    # Wie hoch ist der Anteil weiblicher Bestandsbildnerinnen?

    Das Notebook wertet die Bestände (Datenbestand **BF**) des DLA Marbach über den
    [Datendienst](https://dataservice.dla-marbach.de) aus und ermittelt, wie viele
    Vor- und Nachlässe, Autorenbibliotheken und Sammlungen von Frauen bzw. Männern stammen.
    Die Daten werden bei jedem Aufruf frisch abgefragt, die Auswertung ist damit immer aktuell.

    **Vorgehen**

    1. Alle Bestände mit den Feldern `category`, `categorySub`, `categorySubSub`,
       `parent_id_mv` und `personAt_id_mv` (Bestandsbildner) laden.
    2. Alle als Bestandsbildner verknüpften Personen (PE) inkl. Feld `gender` per
       [Solr Join Query](https://github.com/dla-marbach/dla-opac-dataservice#solr-join-query) laden.
    3. Bestände filtern (Bestandstyp, nur Hauptbestände) und nach Geschlecht auszählen.

    Hinweis: Das Feld `category` ist im Index tokenisiert, eine Suche nach `category:Bestand`
    findet auch *Aufgelöster Bestand der Bibliothek*. Die exakte Filterung erfolgt deshalb im Notebook.
    """)
    return


@app.cell
def _():
    import json
    import sys
    import urllib.parse
    import urllib.request

    import altair as alt
    import marimo as mo
    import pandas as pd

    return alt, json, mo, pd, sys, urllib


@app.cell
def _(mo):
    _default_api = "https://dataservice.dla-marbach.de/v1"
    api_base = (
        mo.cli_args().get("api") or mo.query_params().get("api") or _default_api
    ).rstrip("/")
    return (api_base,)


@app.cell
def _(json, sys, urllib):
    def build_url(base, path, params):
        return f"{base}/{path}?{urllib.parse.urlencode(params, quote_via=urllib.parse.quote)}"

    async def fetch_json(url):
        """Load JSON via pyfetch in the browser (WASM) or urllib in CPython."""
        if "pyodide" in sys.modules:
            from pyodide.http import pyfetch

            response = await pyfetch(url)
            if response.status == 204:
                return []
            if not response.ok:
                raise RuntimeError(f"HTTP {response.status} für {url}")
            return await response.json()
        with urllib.request.urlopen(url, timeout=300) as response:
            if response.status == 204:
                return []
            return json.loads(response.read().decode("utf-8"))

    return build_url, fetch_json


@app.cell
def _(api_base, build_url):
    BF_FIELDS = "id,display,category,categorySub,categorySubSub,parent_id_mv,personAt_id_mv,personAt_display_mv"
    url_bf = build_url(api_base, "records", {"q": "id:BF*", "fields": BF_FIELDS})
    url_pe = build_url(
        api_base,
        "records",
        {
            "q": "id:PE*",
            "fq": "{!join from=personAt_id_mv to=id}id:BF*",
            "fields": "id,display,gender",
        },
    )
    return url_bf, url_pe


@app.cell
async def _(fetch_json, mo, pd, url_bf, url_pe):
    with mo.status.spinner(title="Lade Bestände und Personen aus dem Datendienst …"):
        bf_raw = pd.DataFrame(await fetch_json(url_bf))
        pe_raw = pd.DataFrame(await fetch_json(url_pe))

    for _col in ["display", "category", "categorySub", "categorySubSub"]:
        if _col not in bf_raw:
            bf_raw[_col] = None
    for _col in ["parent_id_mv", "personAt_id_mv", "personAt_display_mv"]:
        if _col not in bf_raw:
            bf_raw[_col] = None
        bf_raw[_col] = bf_raw[_col].apply(
            lambda v: v if isinstance(v, list) else ([] if v is None or v != v else [v])
        )
    for _col in ["id", "display", "gender"]:
        if _col not in pe_raw:
            pe_raw[_col] = None
    gender_by_person = dict(zip(pe_raw["id"], pe_raw["gender"].fillna("ohne Angabe")))
    name_by_person = dict(zip(pe_raw["id"], pe_raw["display"]))
    return bf_raw, gender_by_person, name_by_person


@app.cell(hide_code=True)
def _(bf_raw, mo, pd):
    _main = bf_raw[bf_raw["parent_id_mv"].str.len() == 0]
    _overview = (
        pd.DataFrame(
            {
                "alle Bestände": bf_raw["category"].fillna("(leer)").value_counts(),
                "davon Hauptbestände": _main["category"].fillna("(leer)").value_counts(),
            }
        )
        .fillna(0)
        .astype(int)
        .sort_values("alle Bestände", ascending=False)
        .rename_axis("category")
        .reset_index()
    )
    mo.vstack(
        [
            mo.md(f"## Datengrundlage\n\n{len(bf_raw):,} Bestände geladen, davon {len(_main):,} Hauptbestände (ohne `parent_id_mv`).".replace(",", ".")),
            mo.ui.table(_overview, selection=None, page_size=25, label="Bestandstypen (Feld category)"),
        ]
    )
    return


@app.cell(hide_code=True)
def _(mo):
    VARIANTE_1 = [
        "Bestand",
        "Depositum in der A-Reihe",
        "Geschlossener Bestand der Bibliothek",
        "Geschlossener Bestand der Mediendokumentation",
        "Sammlung Bilder und Objekte",
    ]
    VARIANTE_2_AUSSCHLUSS = [
        "Theaterprogramme der Mediendokumentation",
        "Zeitungsausschnittsammlung der Mediendokumentation",
        "Dokumentensammlung der Mediendokumentation",
        "Einzelautograph",
    ]
    preset = mo.ui.dropdown(
        options=[
            "Variante 1: nur ausgewählte Bestandstypen",
            "Variante 2: alles außer Mediendokumentation und Einzelautographen",
        ],
        value="Variante 2: alles außer Mediendokumentation und Einzelautographen",
        label="Voreinstellung Bestandstypen",
    )
    preset
    return VARIANTE_1, VARIANTE_2_AUSSCHLUSS, preset


@app.cell(hide_code=True)
def _(VARIANTE_1, VARIANTE_2_AUSSCHLUSS, bf_raw, mo, preset):
    _all_categories = sorted(bf_raw["category"].dropna().unique().tolist())
    if preset.value.startswith("Variante 1"):
        _default = [c for c in _all_categories if c in VARIANTE_1]
    else:
        _default = [c for c in _all_categories if c not in VARIANTE_2_AUSSCHLUSS]
    _sub_values = sorted(
        set(bf_raw["categorySub"].dropna()) | set(bf_raw["categorySubSub"].dropna())
    )

    categories = mo.ui.multiselect(
        options=_all_categories, value=_default, label="Bestandstypen (category)"
    )
    only_main = mo.ui.checkbox(value=True, label="nur Hauptbestände (parent_id_mv leer)")
    collection_values = mo.ui.multiselect(
        options=_sub_values,
        value=[v for v in _sub_values if "sammlung" in v.lower()],
        label="Als Sammlung getrennt ausweisen (categorySub / categorySubSub)",
    )
    counting = mo.ui.dropdown(
        options={
            "je Bestand: erste verknüpfte Person": "first",
            "je Bestand: anteilig auf alle Personen verteilt": "fraction",
            "je Bestand: rein weiblich / rein männlich / gemischt": "mixed",
            "je Person: jede Person nur einmal (Dubletten zusammengefasst)": "person",
        },
        value="je Bestand: erste verknüpfte Person",
        label="Zählweise",
    )
    mo.vstack([categories, only_main, collection_values, counting])
    return categories, collection_values, counting, only_main


@app.cell
def _(
    bf_raw,
    categories,
    collection_values,
    gender_by_person,
    name_by_person,
    only_main,
    pd,
):
    def classify(row):
        if row["categorySub"] in collection_values.value or row["categorySubSub"] in collection_values.value:
            return "Sammlung"
        return "Vor-/Nachlass, Autorenbibliothek u.a."

    selection = bf_raw[bf_raw["category"].isin(categories.value)].copy()
    if only_main.value:
        selection = selection[selection["parent_id_mv"].str.len() == 0]
    selection["Gruppe"] = selection.apply(classify, axis=1)
    selection["genders"] = selection["personAt_id_mv"].apply(
        lambda ids: [gender_by_person.get(p, "ohne Angabe") for p in ids]
    )
    selection["Personen"] = selection["personAt_id_mv"].apply(
        lambda ids: "; ".join(str(name_by_person.get(p, p)) for p in ids)
    )

    def mixed_label(genders):
        if not genders:
            return "keine Person verknüpft"
        known = {g for g in genders if g != "ohne Angabe"}
        if not known:
            return "ohne Angabe"
        return next(iter(known)) if len(known) == 1 else "gemischt"

    selection["erste Person"] = selection["genders"].apply(
        lambda g: g[0] if g else "keine Person verknüpft"
    )
    selection["Zusammensetzung"] = selection["genders"].apply(mixed_label)
    selection["Anzahl Personen"] = selection["personAt_id_mv"].str.len()

    detail = selection[
        [
            "id",
            "display",
            "category",
            "categorySub",
            "categorySubSub",
            "Gruppe",
            "Personen",
            "Anzahl Personen",
            "erste Person",
            "Zusammensetzung",
        ]
    ].reset_index(drop=True)

    _exploded = selection[["id", "Gruppe", "personAt_id_mv"]].explode("personAt_id_mv").dropna()
    _exploded["Anteil"] = 1 / _exploded.groupby("id")["id"].transform("count")
    _exploded["Geschlecht"] = _exploded["personAt_id_mv"].map(
        lambda p: gender_by_person.get(p, "ohne Angabe")
    )
    persons_long = _exploded
    _no_person = selection[selection["Anzahl Personen"] == 0]
    no_person = pd.DataFrame(
        {"id": _no_person["id"], "Gruppe": _no_person["Gruppe"], "Geschlecht": "keine Person verknüpft", "Anteil": 1.0}
    )
    return detail, no_person, persons_long, selection


@app.cell
def _(counting, detail, no_person, pd, persons_long):
    def summarize(mode):
        if mode == "first":
            data = detail.assign(Geschlecht=detail["erste Person"], Anzahl=1.0)
        elif mode == "mixed":
            data = detail.assign(Geschlecht=detail["Zusammensetzung"], Anzahl=1.0)
        elif mode == "fraction":
            data = pd.concat([persons_long, no_person]).rename(columns={"Anteil": "Anzahl"})
        else:
            data = (
                persons_long.drop_duplicates(["Gruppe", "personAt_id_mv"])
                .assign(Anzahl=1.0)
            )
        table = (
            data.groupby(["Gruppe", "Geschlecht"], as_index=False)["Anzahl"].sum()
        )
        table["Prozent"] = 100 * table["Anzahl"] / table.groupby("Gruppe")["Anzahl"].transform("sum")
        known = table["Geschlecht"].isin(["weiblich", "männlich"])
        table["Prozent (nur weiblich+männlich)"] = (
            100 * table["Anzahl"] / table[known].groupby("Gruppe")["Anzahl"].transform("sum").reindex(table.index)
        ).where(known)
        return table.sort_values(["Gruppe", "Anzahl"], ascending=[True, False]).reset_index(drop=True)

    result = summarize(counting.value)
    return result, summarize


@app.cell(hide_code=True)
def _(alt, counting, mo, result, selection):
    def _pct(group, gender):
        row = result[(result["Gruppe"] == group) & (result["Geschlecht"] == gender)]
        return 0.0 if row.empty else float(row["Prozent (nur weiblich+männlich)"].iloc[0])

    _stats = []
    for _group in sorted(result["Gruppe"].unique()):
        _stats.append(
            mo.stat(
                value=f"{_pct(_group, 'weiblich'):.1f} %".replace(".", ","),
                label=f"weiblich – {_group}",
                caption=f"männlich: {_pct(_group, 'männlich'):.1f} %".replace(".", ",") + " (ohne unbekannte)",
                bordered=True,
            )
        )

    _chart = (
        alt.Chart(result)
        .mark_bar()
        .encode(
            x=alt.X("Anzahl:Q", title="Anteil", stack="normalize", axis=alt.Axis(format="%")),
            y=alt.Y("Gruppe:N", title=None),
            color=alt.Color("Geschlecht:N"),
            tooltip=["Gruppe", "Geschlecht", alt.Tooltip("Anzahl:Q", format=".1f"), alt.Tooltip("Prozent:Q", format=".1f")],
        )
        .properties(height=120)
    )
    _label = next(k for k, v in counting.options.items() if v == counting.value)

    mo.vstack(
        [
            mo.md(f"## Ergebnis\n\n{len(selection):,} Bestände ausgewählt · Zählweise: *{_label}*".replace(",", ".")),
            mo.hstack(_stats, justify="start"),
            mo.ui.altair_chart(_chart),
            mo.ui.table(result.round(1), selection=None, label="Auszählung"),
        ]
    )
    return


@app.cell(hide_code=True)
def _(mo, pd, summarize):
    _modes = {
        "erste Person": "first",
        "anteilig": "fraction",
        "Zusammensetzung": "mixed",
        "je Person": "person",
    }
    _rows = []
    for _name, _mode in _modes.items():
        _t = summarize(_mode)
        for _group, _g in _t.groupby("Gruppe"):
            _w = _g.loc[_g["Geschlecht"] == "weiblich", "Prozent (nur weiblich+männlich)"].sum()
            _rows.append({"Zählweise": _name, "Gruppe": _group, "weiblich %": round(_w, 1), "Anzahl gesamt": round(_g["Anzahl"].sum(), 1)})
    mo.vstack(
        [
            mo.md(
                "## Vergleich der Zählweisen\n\n"
                "Bestände mit mehreren Bestandsbildnern (z.B. *Döblin, Alfred* und *Döblin, Peter*) "
                "werden je nach Zählweise unterschiedlich gewertet. Der Vergleich zeigt, wie stark "
                "das Ergebnis davon abhängt (Anteil weiblich bezogen auf weiblich+männlich)."
            ),
            mo.ui.table(pd.DataFrame(_rows), selection=None),
        ]
    )
    return


@app.cell(hide_code=True)
def _(detail, mo):
    _multi = detail[detail["Anzahl Personen"] > 1]
    _unknown = detail[~detail["Zusammensetzung"].isin(["weiblich", "männlich"]) & (detail["Anzahl Personen"] <= 1)]
    mo.vstack(
        [
            mo.md("## Details zur Prüfung"),
            mo.ui.tabs(
                {
                    f"Mehrere Personen ({len(_multi)})": mo.ui.table(_multi, selection=None),
                    f"Nicht zuordenbar ({len(_unknown)})": mo.ui.table(_unknown, selection=None),
                    f"Alle ausgewählten Bestände ({len(detail)})": mo.ui.table(detail, selection=None),
                }
            ),
            mo.download(
                data=detail.to_csv(index=False).encode("utf-8"),
                filename="bestandsbildner-gender.csv",
                mimetype="text/csv",
                label="Alle ausgewählten Bestände als CSV herunterladen",
            ),
        ]
    )
    return


@app.cell(hide_code=True)
def _(mo, url_bf, url_pe):
    mo.md(f"""
    ## Verwendete Abfragen

    * Bestände: <{url_bf}>
    * Personen (Join über `personAt_id_mv`): <{url_pe}>

    Eine schnelle Gegenprobe ohne Notebook liefert der Endpunkt `/v1/records/facets`, z.B. die Anzahl
    der Personen je Geschlecht, die als Bestandsbildner eines Hauptbestands verknüpft sind:
    `/v1/records/facets?q=id:PE*&fq={{!join from=personAt_id_mv to=id}}id:BF* AND -parent_id_mv:*&facet=gender&facetMissing=true`
    """)
    return


if __name__ == "__main__":
    app.run()
