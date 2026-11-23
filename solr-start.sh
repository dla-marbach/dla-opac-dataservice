#!/bin/bash
# Start
# Solr läuft ohne Authentifizierung, deshalb standardmäßig nur auf localhost.
# Die Portweiterleitung im Devcontainer und in Codespaces funktioniert damit.
# Andere Adresse nur ausdrücklich, z. B. SOLR_JETTY_HOST=0.0.0.0 bash solr-start.sh
export SOLR_JETTY_HOST="${SOLR_JETTY_HOST:-127.0.0.1}"
solr/bin/solr start --user-managed || true
solr/docker/scripts/wait-for-solr.sh
# Beispieldaten aus dla-opac-transform repo laden
mkdir -p "solr/import"
for f in ak au be bf bi hs ks mm pe se sy th; do
    curl --silent -o solr/import/$f.jsonl -L https://github.com/dla-marbach/dla-opac-transform/raw/refs/heads/main/example/output/$f.jsonl
done
# Beispieldaten indexieren
for f in solr/import/*.jsonl; do
  [ "$(curl --silent --upload-file "$f" 'http://localhost:8983/solr/internformat/update/json/docs?overwrite=true' -X POST -H 'Content-Type: application/json' -o /dev/stderr -w "%{http_code}")" -eq 200 ]
done
[ "$(curl --silent 'http://localhost:8983/solr/internformat/update?commit=true&optimize=true' -o /dev/stderr -w "%{http_code}")" -eq 200 ]
