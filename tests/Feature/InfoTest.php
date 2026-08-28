<?php

namespace Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Tests\Concerns\InteractsWithSolr;
use Tests\TestCase;

class InfoTest extends TestCase
{
    use InteractsWithSolr;

    public function test_info_returns_document_count_and_last_modification(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueDocuments([], 4711);
        $solr->queue(new Response(200, [], (string) json_encode([
            'status' => [
                'testcore' => ['index' => ['lastModified' => '2026-01-02T03:04:05.000Z']],
            ],
        ])));

        $response = $this->get('/v1/info');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json; charset=utf-8')
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertJson([
                'documentCount' => 4711,
                'collectionCount' => count(config('dla_collection')),
                'lastModify' => '2026-01-02T03:04:05.000Z',
                'license' => 'CC0 (Public Domain)',
            ])
            ->assertJsonStructure(['description', 'documentCount', 'collectionCount', 'lastModify', 'license']);
    }

    public function test_info_asks_solr_for_a_count_only_query(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueDocuments([], 1);
        $solr->queue(new Response(200, [], (string) json_encode([
            'status' => ['testcore' => ['index' => ['lastModified' => 'now']]],
        ])));

        $this->get('/v1/info')->assertStatus(200);

        $this->assertSame(2, $solr->requestCount());
        $this->assertSame(['q' => '*:*', 'rows' => '0'], $solr->queryParameters(0));
        $this->assertStringContainsString('admin/cores', $solr->requestUri(1));
        $this->assertSame(['action' => 'STATUS'], $solr->queryParameters(1));
    }

    public function test_info_schema_hides_internal_and_export_fields(): void
    {
        $solr = $this->fakeSolr();
        $solr->queue(new Response(200, [], (string) json_encode([
            'fields' => [
                ['name' => 'id', 'type' => 'string'],
                ['name' => 'display', 'type' => 'text'],
                ['name' => '_version_', 'type' => 'plong'],
                ['name' => 'exportRIS', 'type' => 'string'],
            ],
        ])));

        $response = $this->get('/v1/info/schema');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json; charset=utf-8')
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertExactJson([
                ['name' => 'id', 'type' => 'string'],
                ['name' => 'display', 'type' => 'text'],
            ]);

        $this->assertStringContainsString('testcore/schema/fields', $solr->requestUri(0));
    }
}
