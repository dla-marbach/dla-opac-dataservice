<?php

namespace Tests\Feature;

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Mockery;
use Tests\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
class RecordsCountTest extends TestCase
{
    public function test_records_count_endpoint_returns_document_count(): void
    {
        $mockClient = Mockery::mock('overload:GuzzleHttp\Client');
        $mockClient->shouldReceive('request')
            ->once()
            ->andReturn(
                new GuzzleResponse(200, [], json_encode(['response' => ['numFound' => 42]]))
            );

        $response = $this->getJson('/api/v1/records/count');

        $response->assertStatus(200)
            ->assertJsonStructure(['documentCount'])
            ->assertJson(['documentCount' => 42]);
    }
}
