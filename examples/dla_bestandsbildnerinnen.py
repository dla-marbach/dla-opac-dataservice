import marimo

__generated_with = "0.25.2"
app = marimo.App()


@app.cell(hide_code=True)
def _(mo):
    mo.md(r"""
    # Wie hoch ist der Frauenanteil unter den Bestandsbildnern im DLA?

    Das Notebook wertet die Bestände (Datenbestand **BF**) des DLA Marbach über den
    [Datendienst](https://dataservice.dla-marbach.de) aus und ermittelt, wie viele der als
    Bestandsbildner verknüpften Personen Frauen bzw. Männer sind. Körperschaften als
    Bestandsbildner (z.B. Verlage) werden als eigene Gruppe mitgezählt.
    Die Daten werden bei jedem Aufruf frisch abgefragt, die Auswertung ist damit immer aktuell.

    **Vorgehen**

    1. Alle Bestände mit den Feldern `category`, `categorySub`, `categorySubSub`, `department`,
       `parent_id_mv`, `personAt_id_mv` und `corporationAt_id_mv` (Bestandsbildner) laden.
    2. Alle als Bestandsbildner verknüpften Personen (PE) inkl. Feld `gender` sowie alle
       verknüpften Körperschaften (KS) per
       [Solr Join Query](https://github.com/dla-marbach/dla-opac-dataservice#solr-join-query) laden.
    3. Bestände nach Abteilung, Bestandstyp, Bestandskategorie und Bestandsunterkategorie filtern
       und die verknüpften Bestandsbildner auszählen (jede Person/Körperschaft genau einmal).

    Einzelheiten zur Zählweise stehen im Abschnitt *Methodik* am Ende des Notebooks.
    """)
    return


@app.cell
def _():
    import datetime
    import json
    import sys
    import urllib.parse
    import urllib.request

    import altair as alt
    import marimo as mo
    import pandas as pd

    return alt, datetime, json, mo, pd, sys, urllib


@app.cell
def _():
    # Bestandstypen (Feld category), die in der Voreinstellung abgewählt sind.
    # Sie lassen sich im Filter jederzeit wieder hinzunehmen.
    DEFAULT_EXCLUDED_CATEGORIES = [
        "Theaterprogramme der Mediendokumentation",
        "Zeitungsausschnittsammlung der Mediendokumentation",
        "Dokumentensammlung der Mediendokumentation",
        "Einzelautograph",
    ]
    return (DEFAULT_EXCLUDED_CATEGORIES,)


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
    BF_FIELDS = (
        "id,display,category,categorySub,categorySubSub,department,"
        "parent_id_mv,personAt_id_mv,corporationAt_id_mv"
    )
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
    url_ks = build_url(
        api_base,
        "records",
        {
            "q": "id:KS*",
            "fq": "{!join from=corporationAt_id_mv to=id}id:BF*",
            "fields": "id,display",
        },
    )
    return url_bf, url_ks, url_pe


@app.cell
async def _(datetime, fetch_json, mo, pd, url_bf, url_ks, url_pe):
    with mo.status.spinner(title="Lade Bestände, Personen und Körperschaften aus dem Datendienst …"):
        bf_raw = pd.DataFrame(await fetch_json(url_bf))
        pe_raw = pd.DataFrame(await fetch_json(url_pe))
        ks_raw = pd.DataFrame(await fetch_json(url_ks))
    abrufzeit = datetime.datetime.now()

    for _col in ["id", "display", "category", "categorySub", "categorySubSub", "department"]:
        if _col not in bf_raw:
            bf_raw[_col] = None
    for _col in ["parent_id_mv", "personAt_id_mv", "corporationAt_id_mv"]:
        if _col not in bf_raw:
            bf_raw[_col] = None
        bf_raw[_col] = bf_raw[_col].apply(
            lambda v: v if isinstance(v, list) else ([] if v is None or v != v else [v])
        )
    for _col in ["id", "display", "gender"]:
        if _col not in pe_raw:
            pe_raw[_col] = None
    for _col in ["id", "display"]:
        if _col not in ks_raw:
            ks_raw[_col] = None
    gender_by_person = dict(zip(pe_raw["id"], pe_raw["gender"].fillna("ohne Angabe")))
    name_by_id = {
        **dict(zip(pe_raw["id"], pe_raw["display"])),
        **dict(zip(ks_raw["id"], ks_raw["display"])),
    }
    return abrufzeit, bf_raw, gender_by_person, name_by_id


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


@app.cell
def _(DEFAULT_EXCLUDED_CATEGORIES, bf_raw, mo):
    # Platzhalter für leere Feldwerte, damit sie im Filter auswählbar sind
    DEPT_NONE = "(ohne Abteilung)"
    SUB_NONE = "(ohne Bestandskategorie)"
    SUBSUB_NONE = "(ohne Bestandsunterkategorie)"

    # Filterelemente; angezeigt werden sie gemeinsam in einer der folgenden Zellen
    _all_departments = sorted(bf_raw["department"].fillna(DEPT_NONE).unique().tolist())
    departments = mo.ui.multiselect(
        options=_all_departments,
        value=_all_departments,
        label="**Abteilung** (department)",
        full_width=True,
    )
    _all_categories = sorted(bf_raw["category"].dropna().unique().tolist())
    categories = mo.ui.multiselect(
        options=_all_categories,
        value=[c for c in _all_categories if c not in DEFAULT_EXCLUDED_CATEGORIES],
        label="**Bestandstyp** (category)",
        full_width=True,
    )
    only_main = mo.ui.checkbox(value=True, label="nur Hauptbestände (ohne Teilbestände mit `parent_id_mv`)")
    with_corporations = mo.ui.checkbox(
        value=True,
        label="Körperschaften als Bestandsbildner mitzählen (`corporationAt_id_mv`, z.B. Verlagsbestände)",
    )
    return (
        DEPT_NONE,
        SUBSUB_NONE,
        SUB_NONE,
        categories,
        departments,
        only_main,
        with_corporations,
    )


@app.cell
def _(DEPT_NONE, SUB_NONE, bf_raw, categories, departments, mo):
    # Bestände, die nach Abteilung und Bestandstyp in Frage kommen (Grundlage der Auswahllisten)
    candidates = bf_raw[
        bf_raw["category"].isin(categories.value)
        & bf_raw["department"].fillna(DEPT_NONE).isin(departments.value)
    ]
    _sub_options = sorted(candidates["categorySub"].fillna(SUB_NONE).unique().tolist())
    subcategories = mo.ui.multiselect(
        options=_sub_options,
        value=_sub_options,
        label="**Bestandskategorie** (categorySub)",
        full_width=True,
    )
    return candidates, subcategories


@app.cell
def _(SUBSUB_NONE, SUB_NONE, candidates, mo, subcategories):
    _subsub_options = sorted(
        candidates.loc[
            candidates["categorySub"].fillna(SUB_NONE).isin(subcategories.value), "categorySubSub"
        ]
        .fillna(SUBSUB_NONE)
        .unique()
        .tolist()
    )
    subsubcategories = mo.ui.multiselect(
        options=_subsub_options,
        value=_subsub_options,
        label="**Bestandsunterkategorie** (categorySubSub)",
        full_width=True,
    )
    return (subsubcategories,)


@app.cell(hide_code=True)
def _(
    DEFAULT_EXCLUDED_CATEGORIES,
    categories,
    departments,
    mo,
    only_main,
    subcategories,
    subsubcategories,
    with_corporations,
):
    _excluded = ", ".join(f"*{c}*" for c in DEFAULT_EXCLUDED_CATEGORIES)
    mo.vstack(
        [
            mo.md(
                "## Filter\n\n"
                "Die Auswahl wirkt sich auf alle folgenden Abschnitte aus. "
                f"Voreingestellt abgewählt sind die Bestandstypen {_excluded}. "
                "Die Auswahllisten rechts bzw. unten enthalten nur Werte, die bei der Auswahl "
                "links bzw. oben vorkommen; ändert man dort etwas, werden sie auf *alle* zurückgesetzt."
            ),
            mo.hstack([departments, categories], widths="equal", gap=2, align="start"),
            mo.hstack([subcategories, subsubcategories], widths="equal", gap=2, align="start"),
            only_main,
            with_corporations,
        ],
        gap=1,
    )
    return


@app.cell
def _(
    DEPT_NONE,
    SUBSUB_NONE,
    SUB_NONE,
    bf_raw,
    categories,
    departments,
    gender_by_person,
    name_by_id,
    only_main,
    pd,
    subcategories,
    subsubcategories,
    with_corporations,
):
    selection = bf_raw[
        bf_raw["category"].isin(categories.value)
        & bf_raw["department"].fillna(DEPT_NONE).isin(departments.value)
        & bf_raw["categorySub"].fillna(SUB_NONE).isin(subcategories.value)
        & bf_raw["categorySubSub"].fillna(SUBSUB_NONE).isin(subsubcategories.value)
    ].copy()
    if only_main.value:
        selection = selection[selection["parent_id_mv"].str.len() == 0]
    if not with_corporations.value:
        selection["corporationAt_id_mv"] = [[] for _ in range(len(selection))]

    def _names(ids):
        return "; ".join(str(name_by_id.get(i, i)) for i in ids)

    selection["Personen"] = selection["personAt_id_mv"].apply(_names)
    selection["Körperschaften"] = selection["corporationAt_id_mv"].apply(_names)
    selection["Anzahl Personen"] = selection["personAt_id_mv"].str.len()
    selection["Anzahl Körperschaften"] = selection["corporationAt_id_mv"].str.len()

    bestaende = (
        selection[
            [
                "display",
                "department",
                "category",
                "categorySub",
                "categorySubSub",
                "Personen",
                "Körperschaften",
                "Anzahl Personen",
                "Anzahl Körperschaften",
                "id",
            ]
        ]
        .rename(
            columns={
                "display": "Bestand",
                "department": "Abteilung",
                "category": "Bestandstyp",
                "categorySub": "Bestandskategorie",
                "categorySubSub": "Bestandsunterkategorie",
            }
        )
        .reset_index(drop=True)
    )

    # Je Bestandsbildner: jede Person/Körperschaft genau einmal, mit Anzahl ihrer ausgewählten Bestände
    _cols = ["id", "department", "category", "categorySub", "categorySubSub"]
    _links = pd.concat(
        [
            selection[_cols + ["personAt_id_mv"]]
            .explode("personAt_id_mv")
            .rename(columns={"personAt_id_mv": "bildner"})
            .assign(Art="Person"),
            selection[_cols + ["corporationAt_id_mv"]]
            .explode("corporationAt_id_mv")
            .rename(columns={"corporationAt_id_mv": "bildner"})
            .assign(Art="Körperschaft"),
        ],
        ignore_index=True,
    ).dropna(subset=["bildner"])

    def _join(s):
        return "; ".join(sorted({str(v) for v in s.dropna()}))

    persons = (
        _links.groupby(["bildner", "Art"])
        .agg(
            Bestände=("id", "nunique"),
            Abteilungen=("department", _join),
            Bestandstypen=("category", _join),
            Bestandskategorien=("categorySub", _join),
            Bestandsunterkategorien=("categorySubSub", _join),
        )
        .reset_index()
        .rename(columns={"bildner": "id"})
    )
    persons["Name"] = persons["id"].map(lambda p: name_by_id.get(p, p))
    persons["Geschlecht"] = [
        "Körperschaft" if art == "Körperschaft" else gender_by_person.get(p, "ohne Angabe")
        for p, art in zip(persons["id"], persons["Art"])
    ]
    persons = (
        persons[
            [
                "Name",
                "Art",
                "Geschlecht",
                "Bestände",
                "Abteilungen",
                "Bestandstypen",
                "Bestandskategorien",
                "Bestandsunterkategorien",
                "id",
            ]
        ]
        .sort_values(["Bestände", "Name"], ascending=[False, True])
        .reset_index(drop=True)
    )
    return bestaende, persons, selection


@app.cell
def _(persons):
    GENDER_GROUPS = ["weiblich", "männlich", "ohne Angabe", "Körperschaft"]
    _group = persons["Geschlecht"].where(
        persons["Geschlecht"].isin(["weiblich", "männlich", "Körperschaft"]), "ohne Angabe"
    )
    result = (
        _group.value_counts()
        .reindex(GENDER_GROUPS, fill_value=0)
        .rename_axis("Geschlecht")
        .rename("Anzahl")
        .reset_index()
    )
    _total = result["Anzahl"].sum()
    result["Prozent"] = 100 * result["Anzahl"] / _total if _total else 0.0
    return GENDER_GROUPS, result


@app.cell(hide_code=True)
def _(
    GENDER_GROUPS,
    abrufzeit,
    alt,
    bestaende,
    categories,
    departments,
    mo,
    only_main,
    persons,
    result,
    selection,
    subcategories,
    subsubcategories,
    with_corporations,
):
    def _describe(element):
        """Auswahl kurz beschreiben: 'alle', 'alle außer …' oder die gewählten Werte."""
        _options = list(element.options.values())
        _selected = [o for o in _options if o in element.value]
        _excluded = [o for o in _options if o not in element.value]
        if not _excluded:
            return "alle"
        if not _selected:
            return "keine"
        if len(_excluded) < len(_selected):
            return "alle außer " + ", ".join(f"*{o}*" for o in _excluded)
        return ", ".join(f"*{o}*" for o in _selected)

    def _pct(x):
        return f"{x:.1f} %".replace(".", ",")

    def _num(x):
        return f"{int(x):,}".replace(",", ".")

    # Bestände nach Art der Bestandsbildner
    _has_pe = bestaende["Anzahl Personen"] > 0
    _has_ks = bestaende["Anzahl Körperschaften"] > 0
    _n_pe = int(_has_pe.sum())
    _n_ks_only = int((_has_ks & ~_has_pe).sum())
    _n_none = int((~_has_pe & ~_has_ks).sum())

    _bestand_stats = mo.hstack(
        [
            mo.stat(value=_num(len(selection)), label="Bestände ausgewählt", bordered=True),
            mo.stat(value=_num(_n_pe), label="mit Person als Bestandsbildner", bordered=True),
            mo.stat(value=_num(_n_ks_only), label="nur mit Körperschaft", bordered=True),
            mo.stat(value=_num(_n_none), label="ohne Bestandsbildner", bordered=True),
        ],
        widths="equal",
        gap=1,
    )

    _stats = mo.hstack(
        [
            mo.stat(
                value=_pct(r.Prozent),
                label=r.Geschlecht,
                caption=f"{_num(r.Anzahl)} {'Körperschaften' if r.Geschlecht == 'Körperschaft' else 'Personen'}",
                bordered=True,
            )
            for r in result.itertuples()
        ],
        widths="equal",
        gap=1,
    )

    _data = result.assign(
        Beschriftung=[f"{_pct(p)} ({_num(n)})" for p, n in zip(result["Prozent"], result["Anzahl"])]
    )
    _base = alt.Chart(_data).encode(
        y=alt.Y("Geschlecht:N", sort=GENDER_GROUPS, title=None),
        x=alt.X(
            "Prozent:Q",
            title="Anteil an allen gezählten Bestandsbildnern (%)",
            scale=alt.Scale(domain=[0, 115]),
            axis=alt.Axis(values=[0, 25, 50, 75, 100]),
        ),
    )
    _bars = _base.mark_bar().encode(
        color=alt.Color(
            "Geschlecht:N",
            scale=alt.Scale(domain=GENDER_GROUPS, range=["#d95f02", "#1b9e77", "#b0b0b0", "#7570b3"]),
            legend=None,
        ),
        tooltip=["Geschlecht", "Anzahl", alt.Tooltip("Prozent:Q", format=".1f")],
    )
    _labels = _base.mark_text(align="left", dx=4).encode(text="Beschriftung:N")
    _chart = (_bars + _labels).properties(height=alt.Step(32), width="container")

    # Frauenanteil nur unter Personen mit Geschlechtsangabe als Vergleichswert
    _counts = dict(zip(result["Geschlecht"], result["Anzahl"]))
    _w, _m = _counts.get("weiblich", 0), _counts.get("männlich", 0)
    _share_known = f"{_pct(100 * _w / (_w + _m))}" if (_w + _m) else "–"
    _n_persons = int((persons["Art"] == "Person").sum())
    _n_corps = int((persons["Art"] == "Körperschaft").sum())

    mo.vstack(
        [
            mo.md(
                f"## Ergebnis\n\n"
                f"Stand: {abrufzeit:%d.%m.%Y}\n\n"
                f"**Filter:** Abteilung: {_describe(departments)} · "
                f"Bestandstyp: {_describe(categories)} · "
                f"Bestandskategorie: {_describe(subcategories)} · "
                f"Bestandsunterkategorie: {_describe(subsubcategories)} · "
                f"nur Hauptbestände: {'ja' if only_main.value else 'nein'} · "
                f"Körperschaften mitgezählt: {'ja' if with_corporations.value else 'nein'}\n\n"
                f"### Bestände"
            ),
            _bestand_stats,
            mo.md(
                f"### Bestandsbildner\n\n"
                f"An den ausgewählten Beständen sind {_num(_n_persons)} verschiedene Personen"
                + (f" und {_num(_n_corps)} verschiedene Körperschaften" if with_corporations.value else "")
                + " als Bestandsbildner verknüpft (jede/r einmal gezählt).\n\n"
                "Im DLA werden Teile eines Vor- und Nachlasses getrennt aufbewahrt. Der handschriftliche, "
                "nicht veröffentlichte Teil wird in der Handschriftenabteilung bewahrt und nach "
                "archivarischen Standards katalogisiert. Pressematerialien sowie Ton- und Bildträger "
                "kommen in die Mediendokumentation, der gegenständliche Nachlass sowie Fotografien in die "
                "Abteilung Bilder & Objekte. In der Abteilung Bibliothek wird die Autorenbibliothek des "
                "Autors bzw. der Autorin aufgestellt und nach bibliothekarischen Standards erschlossen.\n\n"
                "Jede Abteilung erstellt in Kallías einen eigenen Datensatz für die Beschreibung ihres "
                "Teils. Alle Datensätze sind aber immer mit dem Bestandsbildner (Normdatensatz) verknüpft. "
                "Je nach enthaltenen Materialien kann es für einen Bestandsbildner bis zu vier Datensätze "
                "für einen Vor- oder Nachlass geben.\n\n"
                "Die Anzahl der Bestandsbildner entspricht daher der Gesamtzahl der im DLA bewahrten "
                "Bestände. Dazu gehören neben Vor- und Nachlässen auch Verlagsarchive, "
                "Sammlerbibliotheken und Spezialsammlungen der Bibliothek.\n\n"
                f"Zum Vergleich: Frauenanteil nur unter den Personen mit Angabe *weiblich* oder "
                f"*männlich*: **{_share_known}**."
            ),
            _stats,
            _chart,
        ]
    )
    return


@app.cell(hide_code=True)
def _(mo):
    mo.md("""
    ## Details zur Prüfung

    "
        "Beide Tabellen lassen sich über die Spaltenköpfe sortieren und filtern, z.B. "
        "Personen mit Geschlecht *ohne Angabe* oder Bestände mit *Anzahl Personen* = 0. "
        "Die CSV-Dateien sind für Excel aufbereitet (Trennzeichen Semikolon, UTF-8 mit BOM).
    """)
    return


@app.function
def to_excel_csv(df):
    """CSV, die sich in deutschsprachigem Excel per Doppelklick korrekt öffnet:
    Semikolon als Trennzeichen und UTF-8 mit BOM (für Umlaute)."""
    return df.to_csv(index=False, sep=";").encode("utf-8-sig")


@app.cell(hide_code=True)
def _(abrufzeit, mo, persons):
    mo.vstack(
        [
            mo.md(f"### Bestandsbildner ({len(persons):,})".replace(",", ".")),
            mo.ui.table(persons, selection=None, page_size=10),
            mo.download(
                data=lambda: to_excel_csv(persons),
                filename=f"bestandsbildner-{abrufzeit:%Y-%m-%d}.csv",
                mimetype="text/csv",
                label="Bestandsbildner als CSV herunterladen",
            ),
        ]
    )
    return


@app.cell(hide_code=True)
def _(abrufzeit, bestaende, mo):
    mo.vstack(
        [
            mo.md(f"### Bestände ({len(bestaende):,})".replace(",", ".")),
            mo.ui.table(bestaende, selection=None, page_size=10),
            mo.download(
                data=lambda: to_excel_csv(bestaende),
                filename=f"bestandsbildner-bestaende-{abrufzeit:%Y-%m-%d}.csv",
                mimetype="text/csv",
                label="Bestände als CSV herunterladen",
            ),
        ]
    )
    return


@app.cell(hide_code=True)
def _(DEFAULT_EXCLUDED_CATEGORIES, abrufzeit, mo, url_bf, url_ks, url_pe):
    _excluded = ", ".join(f"*{c}*" for c in DEFAULT_EXCLUDED_CATEGORIES)
    mo.md(f"""
    ## Methodik

    **Zählweise.** Gezählt werden Bestandsbildner, nicht Bestände. Jede Person (`personAt_id_mv`)
    und jede Körperschaft (`corporationAt_id_mv`), die mindestens einem der ausgewählten Bestände
    als Bestandsbildner zugeordnet ist, geht genau einmal in die Auswertung ein – unabhängig davon,
    wie vielen Beständen sie zugeordnet ist. So wird z.B. ein Verlag mit mehreren Beständen nur
    einmal gezählt. Hat ein Bestand mehrere Bestandsbildner (z.B. *Döblin, Alfred* und
    *Döblin, Peter*), zählen alle beteiligten Personen und Körperschaften. Bestände ohne
    Bestandsbildner gehen nicht in die Prozentwerte ein; sie lassen sich unter *Details zur Prüfung*
    über *Anzahl Personen* = 0 und *Anzahl Körperschaften* = 0 herausfiltern.

    **Bestandszahlen.** Die Kennzahlen unter *Ergebnis › Bestände* zählen dagegen Bestände:
    alle ausgewählten Bestände, davon mit mindestens einer Person als Bestandsbildner, nur mit
    Körperschaft(en) (z.B. Verlagsbestände) und ganz ohne Bestandsbildner.

    **Geschlecht.** Maßgeblich ist das Feld `gender` des Personendatensatzes. Als *ohne Angabe*
    gezählt werden Personen ohne Eintrag in diesem Feld, Personen, zu deren ID die Join-Abfrage
    keinen Datensatz liefert, sowie Personen mit einem anderen Wert als *weiblich* oder *männlich*.
    Körperschaften bilden eine eigene Gruppe *Körperschaft*. Die Prozentwerte beziehen sich auf alle
    gezählten Bestandsbildner (Personen und Körperschaften) und ergeben zusammen 100 %. Zusätzlich
    wird der Frauenanteil nur unter Personen mit Angabe *weiblich* oder *männlich* ausgewiesen.

    **Filter.** Das Feld `category` ist im Index tokenisiert, eine Suche nach `category:Bestand`
    findet auch *Aufgelöster Bestand der Bibliothek*. Deshalb werden alle Bestände geladen und
    Abteilung (`department`), Bestandstyp (`category`), Bestandskategorie (`categorySub`) und
    Bestandsunterkategorie (`categorySubSub`) exakt im Notebook gefiltert. Voreingestellt sind alle
    Werte außer den Bestandstypen {_excluded}. Die Auswahllisten der Bestandskategorien und
    Bestandsunterkategorien enthalten nur Werte, die bei der übergeordneten Auswahl vorkommen.
    Mit der Option *nur Hauptbestände* werden Teilbestände (mit `parent_id_mv`) ausgeschlossen,
    sodass nur die Bestandsbildner der Hauptbestände zählen. Mit der Option *Körperschaften
    mitzählen* lassen sich die Körperschaften aus der Auswertung herausnehmen.

    **Stand.** Die Daten wurden am {abrufzeit:%d.%m.%Y} abgerufen.

    ### Verwendete Abfragen

    * Bestände: <{url_bf}>
    * Personen (Join über `personAt_id_mv`): <{url_pe}>
    * Körperschaften (Join über `corporationAt_id_mv`): <{url_ks}>
    """)
    return


if __name__ == "__main__":
    app.run()