# AGENTS.md

Regeln für KI-Agenten in diesem Repository. Zweck, Aufbau und Befehle des Projekts stehen im README.

## Umgebung
- Lokal und in Codespaces läuft die Entwicklung im Devcontainer (`.devcontainer/`).
- Fehlt ein Werkzeug, benenne es im PR; die Ergänzung gehört in `.devcontainer/`.

## Checks vor Abschluss
- Maßgeblich sind die Schritte in `.github/workflows/`. Führe die Tests lokal genauso aus wie die CI (`vendor/bin/phpunit`). Sicherheitsscans (Trivy, Psalm) laufen nur in der CI; behebe ihre Funde.
- Alle Tests müssen grün sein. Neue Funktionen und Fixes bekommen eigene Tests.

## Git und Pull Requests
- Jede Änderung gehört zu einem Redmine-Ticket. Arbeite in einem Branch `feature/<ticket>-<kurzbeschreibung>` oder `fix/<ticket>-<kurzbeschreibung>`, nie direkt auf `main`. Vergibt dein Werkzeug den Branch-Namen selbst (z. B. `copilot/…`), behalte ihn und nenne die Ticketnummer im PR-Titel. Fehlt die Ticketnummer, frage danach.
- Halte Änderungen klein und auf eine Aufgabe beschränkt. Gemergt wird per Squash, und zwar nur durch Menschen.
- Nenne die Ticketnummer im PR-Titel ohne „#“ (z. B. „… (Ticket 1234)“), verlinke das Ticket in der Beschreibung und beschreibe, was sich ändert und warum. Breaking Changes und manuelle Upgrade-Schritte (z. B. neue Variablen) werden zusätzlich in die Release Notes übernommen.
- Setze Hinweise aus dem Copilot-Review um oder begründe im PR, warum nicht.

## Abhängigkeiten
- Updates von Abhängigkeiten, GitHub Actions und Base Images übernimmt Dependabot. Ändere Versionen nur, wenn die Aufgabe es erfordert.
- Füge neue Abhängigkeiten nur hinzu, wenn die Aufgabe es erfordert. Begründe sie im PR.
- Base Images in `.devcontainer/Dockerfile` immer mit festem Versionstag, nie `latest`.

## Betrieb
- Die Anwendung läuft nicht als Container, sondern als Git-Checkout eines Release-Tags mit PHP und Webserver auf dem Server (siehe „Deployment“ in der `CONTRIBUTING.md`). Eine Datenbank gibt es nicht; der Solr-Index wird außerhalb dieses Repos befüllt.
- Konfiguration ausschließlich über Umgebungsvariablen aus `.env`. Jede neue Variable mit Beispielwert in `.env.example` eintragen.
- Versionen folgen SemVer mit „v“ (`vX.Y.Z`). Tags und Releases legen nur Menschen an.

## No-Gos
- Keine Secrets, Tokens oder Passwörter in Code, Tests, Logs oder Commits.
- Keine personenbezogenen Daten oder Produktivdaten in Code, Tests, Beispieldaten, Logs oder Commits. Test- und Beispieldaten sind synthetisch.
- Tests nicht abschwächen oder löschen und Checks oder Scans nicht deaktivieren oder unterdrücken, um die CI grün zu bekommen.
- Keine Einträge in `.trivyignore`. Ausnahmen für Sicherheitsfunde legen nur Menschen an.
- `.github/` und `.devcontainer/` nur auf ausdrückliche Anweisung ändern.

## Projektspezifisch

### Architektur
- Laravel-8-App: Alle öffentlichen Endpunkte stehen in `routes/api.php` (Präfix `/v1`) und werden in `app/Http/Controllers` umgesetzt. Neue Endpunkte als Controller-Methoden dort ergänzen, Solr-Aufrufe nicht an anderen Stellen verteilen.
- Solr-Zugriffe laufen zentral über `App\Support\SolrClientFactory`, die die Guzzle-Clients aus `config/dla_solr.php` baut. Clients immer über diese Factory erzeugen, damit die Tests sie ersetzen können.
- `transformGivenParameter()` übersetzt Nutzerparameter in Solr-Syntax (sort, rows, start, fields). Neue Filter dort ergänzen, damit Paginierung und Validierung einheitlich bleiben.
- Gestreamt wird in `responseFilter()` und `formattingResponse()`: Sie lesen Solr-Antworten stückweise (json-machine) und antworten bei leeren Ergebnissen mit `204 No Content`. Neue Exportformate in diese Pipeline einhängen, damit große Downloads speicherschonend bleiben.
- `responseFilter()` normalisiert die Namespaces der Felder `exportMODS`/`exportDC` und umschließt sie mit einem Collection-Element. Künftige `exportXYZ`-Felder aus Solr genauso behandeln.
- Sammlungen sind in `config/dla_collection.php` hinterlegt. `/v1/collection/{id}` leitet mit der gespeicherten Abfrage auf `/v1/records` um; IDs numerisch und Abfragen Solr-sicher halten. `info`, `query` und `url` eines Eintrags erscheinen in API-Antworten und müssen zueinander passen.

### Dokumentation und Swagger
- Die Swagger-UI-Routen kommen aus `wotz/laravel-swagger-ui`. `SwaggerUiServiceProvider` bindet dessen Controller an `OverrideOpenApiJsonController`, der über Solrs `/config/requestHandler` die `fields`-Enum dynamisch füllt und die `servers`-URL durch `APP_URL` ersetzt.
- Die Spezifikation liegt in `resources/swagger/openapi.json` (konfiguriert in `config/swagger-ui.php`). `info.version` folgt dem Release-Tag, siehe „Release“ in der `CONTRIBUTING.md`.
- Angepasste Swagger-Views liegen unter `resources/views/vendor/swagger-ui`, die Assets fertig gebaut unter `public/css/swagger-ui.css` und `public/js/swagger-ui-bundle.js`. Es gibt keinen JS/CSS-Build-Schritt.

### Konfiguration
- Benötigte Variablen: `DLA_SOLR_BASE_URI` (mit abschließendem Slash), `DLA_SOLR_BASE_CORE` und `APP_URL` (für die Server-Angabe in Swagger).
- `composer.lock` ist nicht versioniert. Die CI erzeugt sie für den Trivy-Scan selbst.

### Entwicklung und Tests
- Lokal starten mit `php artisan serve`; die Endpunkte liegen unter `http://127.0.0.1:8000/v1` und fragen den konfigurierten Solr an. `solr-install.sh` und `solr-start.sh` richten einen lokalen Solr mit Beispieldaten ein.
- Die Testsuite (`vendor/bin/phpunit`) braucht keinen Solr. Feature-Tests gehören nach `tests/Feature` und nutzen den Trait `Tests\Concerns\InteractsWithSolr`, der `SolrClientFactory` durch `Tests\Support\FakeSolrClientFactory` ersetzt (vorbereitete Guzzle-Antworten, aufgezeichnete Anfragen).
- Die Notebooks in `examples/` fragen die produktive API ab. Sie dienen als Integrationstest, wenn sich Abfragesyntax oder Antwortformate ändern.
- Zum Debuggen von Solr-Abfragen `logger()->debug()` verwenden. Ausgaben per `echo` in den Response-Callbacks zerstören gestreamte Downloads.

### Muster
- Sortierung erwartet Solr-Syntax wie `field asc,other desc`. Nutzereingaben nur nach Prüfung der erlaubten Felder durchreichen, möglichst per Whitelist aus denselben `/config/requestHandler`-Metadaten wie in Swagger.
- Hohe Standardwerte für `size` (derzeit 10.000.000) nur beim Streaming. Für UI- oder Notebook-Helfer ausdrücklich niedrigere Werte setzen, um ungewollt große Exporte zu vermeiden.
