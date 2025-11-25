#!/bin/bash
# Installation
mkdir -p solr
wget -q --compression=auto --show-progress -O solr.tgz https://www.apache.org/dyn/closer.lua/solr/solr/9.8.1/solr-9.8.1-slim.tgz?action=download
tar -xzf solr.tgz -C solr --strip 1 && rm solr.tgz
# Konfiguration aus dla-opac-transform repo laden
mkdir -p "solr/server/solr/dla/conf"
curl -o solr/server/solr/dla/conf/solrconfig.xml -L https://github.com/dla-marbach/dla-opac-transform/raw/refs/heads/main/config/solr/solrconfig.xml
curl -o solr/server/solr/dla/conf/managed-schema -L https://github.com/dla-marbach/dla-opac-transform/raw/refs/heads/main/config/solr/schema.xml
touch solr/server/solr/dla/core.properties solr/server/solr/dla/stopwords.txt solr/server/solr/dla/synonyms.txt
