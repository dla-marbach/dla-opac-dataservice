<?php

namespace App\Support;

use GuzzleHttp\Client;

/**
 * Creates the Guzzle clients used to talk to Solr.
 *
 * Having a single factory keeps the Solr configuration in one place and allows
 * tests to replace the HTTP layer without a running Solr instance.
 */
class SolrClientFactory
{
    public function make(string $baseUri): Client
    {
        return new Client(['base_uri' => $baseUri]);
    }

    public function selectClient(): Client
    {
        return $this->make(config('dla_solr.base_uri') . config('dla_solr.core') . '/select');
    }

    public function coreClient(string $path): Client
    {
        return $this->make(config('dla_solr.base_uri') . config('dla_solr.core') . '/' . $path);
    }

    public function adminClient(): Client
    {
        return $this->make(config('dla_solr.base_uri') . 'admin/');
    }
}
