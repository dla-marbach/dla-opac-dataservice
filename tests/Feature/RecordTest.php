<?php

namespace Tests\Feature;

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Mockery;
use Tests\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
class RecordTest extends TestCase
{
    public function test_record_endpoint_with_valid_id_returns_200_with_mocked_document(): void
    {
        $mockClient = Mockery::mock('overload:GuzzleHttp\Client');
        $mockClient->shouldReceive('request')
            ->twice()
            ->andReturn(
                new GuzzleResponse(200, [], json_encode([
                    'response' => [
                        'numFound' => 1,
                        'docs' => [['id' => 'DLA0001234', 'title' => 'Test']],
                    ],
                ])),
                new GuzzleResponse(200, [], json_encode([
                    'response' => [
                        'numFound' => 1,
                        'docs' => [['id' => 'DLA0001234', 'title' => 'Test']],
                    ],
                ]))
            );

        $response = $this->getJson('/api/v1/record/DLA0001234?fields=title');

        $response->assertStatus(200);
    }

    public function test_record_endpoint_with_invalid_id_returns_404(): void
    {
        $response = $this->getJson('/api/v1/record/invalid-id');

        $response->assertStatus(404);
    }
}
