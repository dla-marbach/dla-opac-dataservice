<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithSolr;
use Tests\TestCase;

class CollectionTest extends TestCase
{
    use InteractsWithSolr;

    public function test_collections_lists_every_configured_collection(): void
    {
        $this->fakeSolr();

        $response = $this->get('/v1/collections');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json; charset=utf-8')
            ->assertHeader('Access-Control-Allow-Origin', '*');

        $collections = $response->json();
        $configured = config('dla_collection');

        $this->assertCount(count($configured), $collections);

        foreach ($collections as $collection) {
            $this->assertArrayHasKey($collection['id'], $configured);
            $this->assertSame($configured[$collection['id']]['info'], $collection['name']);
            $this->assertSame($configured[$collection['id']]['url'], $collection['url']);
        }
    }

    public function test_collection_redirects_to_records_with_the_configured_query(): void
    {
        $this->fakeSolr();
        $configured = config('dla_collection');
        $id = (string) array_key_first($configured);

        $response = $this->get('/v1/collection/' . $id);

        $response->assertStatus(302);

        $location = $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $parameters);

        $this->assertStringContainsString('/v1/records', (string) $location);
        $this->assertSame($configured[$id]['query'], $parameters['q']);
        $this->assertSame('json', $parameters['format']);
    }

    public function test_collection_redirect_keeps_format_and_paging_parameters(): void
    {
        $this->fakeSolr();
        $id = (string) array_key_first(config('dla_collection'));

        $response = $this->get('/v1/collection/' . $id . '.mods?from=5&size=10&fields=id&sort=' . urlencode('id asc'));

        $response->assertStatus(302);

        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $parameters);

        $this->assertSame('.mods', $parameters['format']);
        $this->assertSame('5', $parameters['from']);
        $this->assertSame('10', $parameters['size']);
        $this->assertSame('id', $parameters['fields']);
        $this->assertSame('id asc', $parameters['sort']);
    }

    public function test_unknown_collection_id_does_not_query_solr(): void
    {
        $solr = $this->fakeSolr();

        $this->get('/v1/collection/9999999999');

        $this->assertSame(0, $solr->requestCount());
    }

    public function test_non_numeric_collection_id_is_not_routed(): void
    {
        $solr = $this->fakeSolr();

        $this->get('/v1/collection/abc')->assertStatus(404);

        $this->assertSame(0, $solr->requestCount());
    }
}
