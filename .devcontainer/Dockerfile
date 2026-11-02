# Base Image mit festem Versionstag, Updates über Dependabot.
# PHP 8.3 und Composer wie in der CI (ubuntu-24.04) und auf dem Server empfohlen.
FROM mcr.microsoft.com/devcontainers/php:4.1.7-8.3-trixie

# Java für den lokalen Solr (solr-install.sh, solr-start.sh).
RUN apt-get update \
    && apt-get install -y --no-install-recommends openjdk-21-jre-headless \
    && rm -rf /var/lib/apt/lists/*

USER vscode
