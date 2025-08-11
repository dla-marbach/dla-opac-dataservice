## DLA Data+

Offener Zugang zu den Daten des DLA Marbach auf Basis forschungsprojektbezogener Fragestellungen

* Direkt zum Datendienst: https://dataservice.dla-marbach.de
* Datenmodell: https://github.com/dla-marbach/dla-opac-transform/tree/main/docs
* Webseite: https://www.dla-marbach.de/katalog/dla-dataplus/

Der Datendienst DLA Data+ bietet einen offenen Zugang zu den Daten des DLA Marbach auf Basis forschungsprojektbezogener Fragestellungen. Die offene, gut dokumentierte Schnittstelle soll ermöglichen, mit selbst formulierten Abfragen Daten in verschiedenen Formaten zu exportieren, zu erforschen und in weitere, eigene Umgebungen zu integrieren.

Die Daten können über eine offene Schnittstelle (CC0-Lizenz) in unterschiedlichen Datenformaten (vorerst JSON, RIS, MODS, DC) heruntergeladen werden.

Die Entwicklung erfolgte exemplarisch am Corpus des Quellenrepertoriums der Exilbibliotheken von Alfred Döblin und Siegfried Kracauer. Das Projekt wurde als [Kooperationsprojekt über Text+](https://text-plus.org/vernetzung/kooperationsprojekte/) als Teil der Nationalen Forschungsdateninfrastruktur (NFDI) gefördert ([Weitere Informationen zum Projekt](https://www.dla-marbach.de/bibliothek/projekte/text-kooperationsprojekt-dla-data/)).

### Technik

Der Datendienst basiert auf einem separaten Solr-Index, der mit den Daten des DLA Katalog befüllt wird.

Implementiert wurde eine offene API auf Basis der [OpenAPI](https://swagger.io/specification/) Spezifikation. Mit dem Tool [Swagger UI](https://swagger.io/tools/swagger-ui/) werden die Suchparameter öffentlich dokumentiert, mit einer Möglichkeit, diese an Beispielen interaktiv auszuprobieren.

Die Schnittstellen-Endpunkte werden dabei über das auf PHP basierende Framework [Laravel](https://laravel.com/) bereitgestellt und ermöglichen die Manipulation der Solr-Ausgabe, um die entsprechenden Ausgabeformate bereitstellen zu können.