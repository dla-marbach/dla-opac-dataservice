<?php

namespace Tests\Concerns;

use App\Http\Controllers\Controller;
use App\Support\SolrClientFactory;
use Tests\Support\FakeSolrClientFactory;

trait InteractsWithSolr
{
    protected FakeSolrClientFactory $solr;

    protected function fakeSolr(): FakeSolrClientFactory
    {
        Controller::resetDefaultFieldListCache();

        $this->solr = new FakeSolrClientFactory();
        $this->app->instance(SolrClientFactory::class, $this->solr);

        return $this->solr;
    }

    /**
     * A minimal Solr document containing all export fields.
     *
     * @return array<string, mixed>
     */
    protected function exampleDocument(string $id = 'DLA0000001'): array
    {
        return [
            'id' => $id,
            'display' => 'Huch, Ricarda: Der große Krieg in Deutschland',
            'filterLocation_mv' => ['Marbach'],
            'exportRIS' => "TY  - BOOK\r\nTI  - Der große Krieg in Deutschland\r\nER  - ",
            'exportMODS' => '<mods xmlns="http://www.loc.gov/mods/v3" version="3.8"><titleInfo><title>Der große Krieg in Deutschland</title></titleInfo></mods>',
            'exportDC' => '<oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Der große Krieg in Deutschland</dc:title></oai_dc:dc>',
        ];
    }
}
