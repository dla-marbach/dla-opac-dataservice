<?php

namespace Tests\Feature;

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Mockery;
use Tests\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
class InfoTest extends TestCase
{
    public function test_info_endpoint_returns_expected_structure_and_cors_header(): void
    {
        $mockClient = Mockery::mock('overload:GuzzleHttp\Client');
        $mockClient->shouldReceive('request')
            ->twice()
            ->andReturn(
                new GuzzleResponse(200, [], json_encode(['response' => ['numFound' => 42]])),
                new GuzzleResponse(200, [], json_encode([
                    'status' => [
                        config('dla_solr.core') => [
                            'index' => ['lastModified' => '2026-01-01T00:00:00Z'],
                        ],
                    ],
                ]))
            );

        $response = $this->getJson('/api/v1/info');

        $response->assertStatus(200)
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertJsonStructure(['documentCount', 'collectionCount', 'lastModify', 'license']);
    }
}
