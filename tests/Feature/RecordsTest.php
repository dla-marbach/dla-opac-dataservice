<?php

namespace Tests\Feature;

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Mockery;
use Tests\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
class RecordsTest extends TestCase
{
    public function test_records_endpoint_with_q_parameter_returns_200_or_204(): void
    {
        $this->mockRecordsRequestResponses();

        $response = $this->getJson('/api/v1/records?q=*:*&fields=title');

        $this->assertContains($response->status(), [200, 204]);
    }

    public function test_records_endpoint_with_size_parameter_returns_200_or_204(): void
    {
        $this->mockRecordsRequestResponses();

        $response = $this->getJson('/api/v1/records?size=5&fields=title');

        $this->assertContains($response->status(), [200, 204]);
    }

    public function test_records_endpoint_with_format_json_parameter_returns_200_or_204(): void
    {
        $this->mockRecordsRequestResponses();

        $response = $this->getJson('/api/v1/records?format=json&q=*:*&fields=title');

        $this->assertContains($response->status(), [200, 204]);
    }

    private function mockRecordsRequestResponses(): void
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
    }
}
