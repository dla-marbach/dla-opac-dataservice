# DLA Data+

Der Datendienst DLA Data+ bietet einen offenen Zugang (CC0-Lizenz) zu den Katalogdaten des DLA Marbach. Über die Schnittstelle lassen sich individuelle Abfragen durchführen und die Daten in unterschiedlichen Formaten (JSON, DC, MODS, RIS) exportieren. So können die Daten erforscht und in eigene Forschungsumgebungen eingebunden werden.

* Direkt zum Datendienst: https://dataservice.dla-marbach.de
* Datenmodell: https://github.com/dla-marbach/dla-opac-transform/blob/main/docs/README.md
* Feldliste: https://github.com/dla-marbach/dla-opac-transform/blob/main/docs/internformat.csv
* Webseite: https://www.dla-marbach.de/katalog/dla-dataplus/

Die Entwicklung erfolgte exemplarisch am Corpus des Quellenrepertoriums der Exilbibliotheken von Alfred Döblin und Siegfried Kracauer. Das Projekt wurde als [Kooperationsprojekt über Text+](https://text-plus.org/vernetzung/kooperationsprojekte/) als Teil der Nationalen Forschungsdateninfrastruktur (NFDI) gefördert ([Weitere Informationen zum Projekt](https://www.dla-marbach.de/bibliothek/projekte/text-kooperationsprojekt-dla-data/)).

## Notebooks

Beispiele für komplexe Fragestellungen an die Katalogdaten des DLA, gelöst mit Python:
* Wie hoch ist der Anteil an publizierenden Frauen bei den Verlagen Cotta, Insel und Rotbuch?
  * Komplexe Lösung mit lokaler Filterung: [examples/gender-verlage.ipynb](gender-verlage.ipynb)
  * Vereinfachte Lösung mit Solr Join Query: [examples/gender-verlage-simple.ipynb](gender-verlage-simple.ipynb)
* Wie ist die Verteilung von Primär- und Sekundärliteratur bei einzelnen Autorinnen und Autoren über die Jahre?
* Wie ist die Verteilung von Übersetzungen (sprachlich wie zeitlich)?

## Beispielanfragen

Der Parameter `size=50` liefert nur die ersten 50 Treffer und ist hier bei den meisten Beispielen angefügt, um die Ladezeit zu begrenzen.

### Funktionen der API

* Alle Felder:<br>
[https://dataservice.dla-marbach.de/v1/records? **q=Marbach** &size=50](https://dataservice.dla-marbach.de/v1/records?q=Marbach&size=50)

* Feldsuche:<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterLocation_mv:Marbach** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterLocation_mv:Marbach&size=50)

* Ausgabe reduzieren auf bestimmte Felder:<br>
[https://dataservice.dla-marbach.de/v1/records?q=Marbach **&fields=id,filterLocation_mv** &size=50](https://dataservice.dla-marbach.de/v1/records?q=Marbach&fields=id,filterLocation_mv&size=50)

* Paginierung:<br>
[https://dataservice.dla-marbach.de/v1/records?q=Marbach **&from=51** &size=50](https://dataservice.dla-marbach.de/v1/records?q=Marbach&from=51&size=50)

* Formate `(json, jsonl, dc, mods, ris`):<br>
[https://dataservice.dla-marbach.de/v1/records?q=Marbach **&format=dc** &size=50](https://dataservice.dla-marbach.de/v1/records?q=Marbach&format=dc&size=50)

* Einzelner Datensatz:<br>
[https://dataservice.dla-marbach.de/v1/records? **q=id:AK00972492**](https://dataservice.dla-marbach.de/v1/records?q=id:AK00972492)

* Anzahl der Datensätze:<br>
[https://dataservice.dla-marbach.de/v1/ **records/count?** q=Marbach](https://dataservice.dla-marbach.de/v1/records/count?q=Marbach)

### Suchoperatoren

* Platzhalter (`*`):<br>
[https://dataservice.dla-marbach.de/v1/records? **q=Mar**\* &size=50](https://dataservice.dla-marbach.de/v1/records?q=Mar*&size=50)

* Kodierung von Leerzeichen (`%20`) im Suchbegriff:<br>
[https://dataservice.dla-marbach.de/v1/records? **q=Ricarda%20Huch** &size=50](https://dataservice.dla-marbach.de/v1/records?q=Ricarda%20Huch&size=50)

* Phrasensuche mit Anführungszeichen (`%22`):<br>
[https://dataservice.dla-marbach.de/v1/records? **q=%22Huch,%20Ricarda%22** &size=50](https://dataservice.dla-marbach.de/v1/records?q=%22Huch,%20Ricarda%22&size=50)

* Filter (`%20AND%20`):<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterLocation_mv:Marbach%20AND%20filterType:Audio** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterLocation_mv:Marbach%20AND%20filterType:Audio&size=50)

* Ausschluss (`%20NOT%20`):<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterLocation_mv:Marbach%20NOT%20filterType:Audio** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterLocation_mv:Marbach%20NOT%20filterType:Audio&size=50)

### Filtern wie im Katalog

* nur digitale Medien:<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterDigital:true** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterDigital:true&size=50)

* Medientyp (`Gedrucktes, Handschriften, Normdaten, Audio, Bilder und Objekte, Video`):<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterType:Audio** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterType:Audio&size=50)

* Form und Inhalt (z.B. `Brief, Aufsatz, Prosa`):<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterFormContent_mv:Prosa** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterFormContent_mv:Prosa&size=50)

* Medium (z.B. `Beitrag, Buch, Zeitschriftenheft`):<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterMedium_mv:Buch** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterMedium_mv:Buch&size=50)

* Person/Körperschaft:<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterAuthority_mv:%22Huch,%20Ricarda%20(1864–1947)%22** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterAuthority_mv:%22Huch,%20Ricarda%20(1864–1947)%22&size=50)

* Person/Körperschaft mit Relation (`Von, An, Über, Unter`):<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterAuthorityRelation_mv:%22Huch,%20Ricarda%20(1864–1947)␝Von%22** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterAuthorityRelation_mv:%22Huch,%20Ricarda%20(1864–1947)␝Von%22&size=50)

* Person/Körperschaft mit Funktion (z.B. `Verfasser/Urheber, Adressat, Korrespondent`):<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterAuthorityRole_mv:%22Huch,%20Ricarda%20(1864–1947)␝Verfasser/Urheber%22** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterAuthorityRole_mv:%22Huch,%20Ricarda%20(1864–1947)␝Verfasser/Urheber%22&size=50)

* Zeit (Solr DateRangeField Syntax):<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterDateRange_mv:[1982%20TO%201983]** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterDateRange_mv:[1982%20TO%201983]&size=50)

* Thema (z.B. `Sekundärliteratur, Erzählende Prosa, Person und Werk als Thema`)<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterSubject_mv:%22Person%20und%20Werk%20als%20Thema%22** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterSubject_mv:%22Person%20und%20Werk%20als%20Thema%22&size=50)

* Neu im Katalog (Solr DateRangeField Syntax):<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterRecent:NOW-7DAYS** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterRecent:NOW-7DAYS&size=50)

* Sprache (z.B. `Deutsch, Englisch, Französisch`):<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterLanguage_mv:Englisch** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterLanguage_mv:Englisch&size=50)

* Sprache mit Typ (`Original` oder `Übersetzung`):<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterLanguageType_mv:Englisch␝Übersetzung** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterLanguageType_mv:Englisch␝Übersetzung&size=50)

* Ort (z.B. `Berlin, Stuttgart, München`):<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterLocation_mv:Marbach** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterLocation_mv:Marbach&size=50)

* Ort mit Funktion (z.B. `Erscheinungsort, Wirkungsort, Provenienzort`):<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterLocationType_mv:Marbach␝Erscheinungsort** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterLocationType_mv:Marbach␝Erscheinungsort&size=50)

* Sammlung (z.B. `COTTA:Briefe, A:Jünger, Ernst, SUA:Suhrkamp/03 Lektorate`):<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterCollection_mv:%22COTTA:Briefe%22** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterCollection_mv:%22COTTA:Briefe%22&size=50)

* Datenbestand (z.B. `Bibliotheksmaterialien, Handschriften Einzelnachweise, Personen`):<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterSource:%22Handschriften%20Einzelnachweise%22** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterSource:%22Handschriften%20Einzelnachweise%22&size=50)

* Bibliographie (z.B. `Döblin-Bibliographie, Kracauer-Bibliographie, Schiller-Bibliographie`):<br>
[https://dataservice.dla-marbach.de/v1/records? **q=filterBibliography_mv:%22Döblin-Bibliographie%22** &size=50](https://dataservice.dla-marbach.de/v1/records?q=filterBibliography_mv:%22Döblin-Bibliographie%22&size=50)

### Solr Join Query

* Personen mit Geschlechtsangabe "weiblich", die Urheber von Publikationen des Verlags Cotta sind:
  * Count: https://dataservice.dla-marbach.de/v1/records/count?q=id:PE*%20AND%20gender:weiblich%20AND%20{!join%20from=creator_id_mv%20to=id}publisher_display_mv:Cotta
  * Datensätze: https://dataservice.dla-marbach.de/v1/records?q=id:PE*%20AND%20gender:weiblich%20AND%20{!join%20from=creator_id_mv%20to=id}publisher_display_mv:Cotta

## Technik

Der Datendienst basiert auf einem separaten Solr-Index, der mit den Daten des DLA Katalog befüllt wird.

Implementiert wurde eine offene API auf Basis der [OpenAPI](https://swagger.io/specification/) Spezifikation. Mit dem Tool [Swagger UI](https://swagger.io/tools/swagger-ui/) werden die Suchparameter öffentlich dokumentiert, mit einer Möglichkeit, diese an Beispielen interaktiv auszuprobieren.

Die Schnittstellen-Endpunkte werden dabei über das auf PHP basierende Framework [Laravel](https://laravel.com/) bereitgestellt und ermöglichen die Manipulation der Solr-Ausgabe, um die entsprechenden Ausgabeformate bereitstellen zu können.

## Entwicklungsumgebung

Datendienst installieren und starten:

```
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

Solr mit Beispieldaten installieren und starten:

```
bash solr-install.sh
bash solr-start.sh
```
