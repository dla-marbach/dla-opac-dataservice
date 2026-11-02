# Ablauf bei neuem Feature oder Fix

Die verbindlichen Grundsätze (Branching, Prüfungen, Secrets, Betrieb) stehen in der [DevOps-Vorlage](https://github.com/dla-marbach/devops). Regeln für KI-Agenten stehen in der `AGENTS.md`.

Der Datendienst läuft nicht als Container, sondern als Git-Checkout eines Release-Tags mit PHP und Webserver auf dem Server. Release, Deployment und Rollback sind deshalb nach „Projekte ohne Container-Betrieb“ in der DevOps-Vorlage beschrieben.

## Entwicklung

1. **Ticket:** Redmine-Ticket anlegen oder übernehmen. Darin die geplante Änderung, wesentliche Schritte und den Abschluss dokumentieren.
2. **Branch anlegen:** `git switch main && git pull && git switch -c feature/1234-kurzbeschreibung` (bzw. `fix/…`, mit Ticketnummer). Branches, die KI-Agenten selbst anlegen (z. B. `copilot/…`), behalten ihren Namen; die Ticketnummer steht dann im PR-Titel.
3. **Im Devcontainer entwickeln:** Codespace auf dem Branch oder lokal „Reopen in Container“. Tests ergänzen und lokal ausführen (`vendor/bin/phpunit`). Die Sicherheitsscans (Trivy, Psalm) laufen in der CI.
4. **Pushen und Pull Request nach `main` öffnen:**
   - Titel mit Ticketnummer ohne „#“, z. B. „CSV-Export für Ergebnisse (Ticket 1234)“. `#1234` würde auf GitHub verlinken statt auf Redmine.
   - Beschreibung: Link zum Ticket, was sich ändert und warum, Breaking Changes und manuelle Upgrade-Schritte.
5. **Copilot-Review anfordern** (unter „Reviewers“). Hinweise umsetzen oder im PR begründen, warum nicht.
6. **CI abwarten:** Alle Checks müssen grün sein, bei Funden nachbessern. Ist `main` weitergegangen, „Update branch“ und erneut abwarten.
7. **Squash-Merge:** Prüfen, dass die Ticketnummer in der Commit-Nachricht steht. Danach lokal aufräumen: `git switch main && git pull --prune && git branch -D feature/1234-kurzbeschreibung`

## Release

Releases und Tags legen nur Menschen an. Versioniert wird nach [SemVer](https://semver.org/lang/de/) mit „v“ (z. B. `v1.2.0`). `composer.json` enthält bewusst kein `version`-Feld, da Composer die Version aus dem Git-Tag ableitet.

1. **Version festlegen:** Patch (`v1.2.1`) für Fixes, Minor (`v1.3.0`) für Features, Major (`v2.0.0`) für Breaking Changes. Maßgeblich ist die größte Änderung seit dem letzten Release.
2. **Version in der API-Dokumentation setzen:** `info.version` in `resources/swagger/openapi.json` per Pull Request auf die neue Version setzen (ohne „v“), auch wenn sich an den dokumentierten Endpunkten nichts geändert hat (z. B. bei reinem Refactoring). Die Versionsnummer folgt so immer dem Release-Tag.
3. **CI prüfen:** Der letzte Lauf auf `main` muss grün sein.
4. **Release anlegen:** „Releases“ → „Draft a new release“, neuen Tag auf `main`, „Generate release notes“. Breaking Changes und manuelle Upgrade-Schritte (z. B. neue Variablen in `.env`) ergänzen, dann „Publish release“. Tags werden nie verschoben oder wiederverwendet; ein fehlerhaftes Release wird durch ein neues Patch-Release ersetzt.

## Deployment

Zuerst in der Testumgebung, danach mit demselben Release-Tag in der Produktion. Produktiv läuft immer ein Release-Tag, nie `main`.

Voraussetzungen auf dem Server: PHP >= 7.3 (empfohlen 8.3) mit den von Laravel 8 benötigten Extensions, Composer sowie ein Webserver (Apache/nginx), dessen Document Root auf `public/` zeigt. Ein separater Solr-Index muss erreichbar sein; eine Datenbank wird nicht benötigt.

### Erstinstallation

```sh
git clone https://github.com/dla-marbach/dla-opac-dataservice.git
cd dla-opac-dataservice
git checkout v1.2.0   # aktuellsten Release-Tag einsetzen
composer install --no-dev --optimize-autoloader
cp .env.example .env
chmod 600 .env
```

In `.env` die Werte der Umgebung setzen:

```sh
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dataservice.dla-marbach.de
DLA_SOLR_BASE_URI=<URL des Solr-Servers, mit abschließendem Slash>
DLA_SOLR_BASE_CORE=<Name des Solr-Cores>
```

```sh
php artisan key:generate
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Schreibrechte für den Webserver-Benutzer auf `storage/` und `bootstrap/cache/` sicherstellen. Die `.env` in der Passwortverwaltung sichern.

### Update

Im Installationsverzeichnis auf dem Server:

1. **Vorbereiten:** Release Notes lesen. Bisherige Version notieren (`git describe --tags`) und die `.env` sichern: `cp -p .env .env.backup-$(date +%F_%H%M)`. Neue Variablen aus `.env.example` in die `.env` übernehmen.
2. **Version wechseln:**

   ```sh
   git fetch --tags
   git checkout v1.3.0
   composer install --no-dev --optimize-autoloader
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

3. **Manuelle Upgrade-Schritte** aus den Release Notes ausführen, falls vorhanden.
4. **Neu laden:** Läuft PHP mit OPcache, PHP-FPM bzw. den Webserver neu starten, damit der neue Code geladen wird.
5. **Prüfen:** `/v1/info` und eine Beispielabfrage unter `/v1/records` aufrufen, Swagger UI kurz im Browser testen.
6. **Produktion:** Läuft das Release in der Testumgebung fehlerfrei, die Schritte 1–5 auf dem Produktionsserver wiederholen. Sonst dort zurückrollen und den Fix als neues Patch-Release ausliefern.

## Rollback

Der Datendienst hält keine eigenen Daten; der Solr-Index wird außerhalb dieses Repos befüllt und gesichert. Ein Rollback betrifft deshalb nur Code und `.env`.

1. **Alte Version auschecken:** `git checkout <bisheriger Tag>`, dann `composer install --no-dev --optimize-autoloader`.
2. **Konfiguration zurückspielen**, falls das Update die `.env` geändert hat: `cp -p .env.backup-<zeitstempel> .env`
3. **Caches neu aufbauen und neu laden:** `php artisan config:cache && php artisan route:cache && php artisan view:cache`, PHP-FPM bzw. Webserver neu starten, prüfen wie beim Deployment.

## Projektspezifisch

- Kein Container-Betrieb: Release, Deployment und Rollback wie oben statt Image, `compose.yaml` und ghcr.io. Entsprechend entfallen Image-Build, Image-Scan, Smoketest und der wöchentliche Scan des Release-Images.
- Zusätzlich zu Trivy prüft die CI den Code mit der Sicherheitsanalyse von Psalm (Ergebnisse unter „Security“ → „Code scanning“).
