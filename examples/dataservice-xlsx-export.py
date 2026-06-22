import marimo

__generated_with = "0.13.0"
app = marimo.App(width="medium")


@app.cell
def description():
    import marimo as mo

    mo.md(
        """
        # DLA Dataservice – Daten abrufen und als XLSX exportieren

        Dieses Notebook ruft Daten aus dem [DLA Dataservice](https://dataservice.dla-marbach.de) ab und ermöglicht den Export als XLSX-Datei.

        ## Konfiguration

        Passen Sie die Parameter unten an, um die gewünschten Daten abzurufen.
        """
    )
    return (mo,)


@app.cell
def parameters(mo):
    query_input = mo.ui.text(
        value="*",
        label="Suchanfrage (q)",
        full_width=True,
    )
    fields_input = mo.ui.text(
        value="id,title,creator_display_mv,date_display",
        label="Felder (fields, kommagetrennt)",
        full_width=True,
    )
    size_input = mo.ui.number(
        value=100,
        start=1,
        stop=10000,
        label="Anzahl Ergebnisse (size)",
    )
    filter_input = mo.ui.text(
        value="",
        label="Filterabfrage (fq, optional)",
        full_width=True,
    )
    mo.md(
        f"""
        ## Parameter

        {query_input}
        {fields_input}
        {size_input}
        {filter_input}
        """
    )
    return fields_input, filter_input, query_input, size_input


@app.cell
def fetch_data(fields_input, filter_input, mo, query_input, size_input):
    import requests
    import pandas as pd

    base_url = "https://dataservice.dla-marbach.de/v1/records"
    params = {
        "q": query_input.value,
        "fields": fields_input.value,
        "size": size_input.value,
    }
    if filter_input.value:
        params["fq"] = filter_input.value

    try:
        response = requests.get(base_url, params=params)
        response.raise_for_status()
    except requests.exceptions.RequestException as e:
        mo.stop(True, mo.md(f"**Fehler bei der API-Abfrage:** {e}"))

    data = response.json()
    df = pd.DataFrame(data)
    df
    return df, pd


@app.cell
def show_info(df, mo):
    if df.empty:
        mo.stop(True, mo.md("**Keine Ergebnisse gefunden.** Bitte passen Sie die Suchparameter an."))

    mo.md(
        f"""
        ## Ergebnis

        **{len(df)} Datensätze** abgerufen mit **{len(df.columns)} Feldern**: {', '.join(df.columns.tolist())}
        """
    )
    return


@app.cell
def export_xlsx(df, mo, pd):
    import io

    # DataFrame als XLSX in einen Byte-Buffer schreiben
    buffer = io.BytesIO()
    with pd.ExcelWriter(buffer, engine="openpyxl") as writer:
        df.to_excel(writer, index=False, sheet_name="DLA Daten")
    xlsx_bytes = buffer.getvalue()

    download_button = mo.download(
        data=xlsx_bytes,
        filename="dla-dataservice-export.xlsx",
        mimetype="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        label="📥 Als XLSX herunterladen",
    )

    mo.md(
        f"""
        ## Export

        {download_button}
        """
    )
    return


if __name__ == "__main__":
    app.run()
