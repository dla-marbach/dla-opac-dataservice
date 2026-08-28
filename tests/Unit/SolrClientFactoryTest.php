<?php

namespace Tests\Unit;

use App\Support\SolrClientFactory;
use Tests\TestCase;

class SolrClientFactoryTest extends TestCase
{
    public function test_clients_are_built_from_the_solr_configuration(): void
    {
        config(['dla_solr.base_uri' => 'http://solr.example/', 'dla_solr.core' => 'opac']);

        $factory = new SolrClientFactory();

        $this->assertSame(
            'http://solr.example/opac/select',
            (string) $factory->selectClient()->getConfig('base_uri')
        );
        $this->assertSame(
            'http://solr.example/opac/schema/',
            (string) $factory->coreClient('schema/')->getConfig('base_uri')
        );
        $this->assertSame(
            'http://solr.example/admin/',
            (string) $factory->adminClient()->getConfig('base_uri')
        );
    }
}
