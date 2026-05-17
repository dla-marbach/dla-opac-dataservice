<?php

namespace Tests\Feature;

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Request;
use Tests\TestCase;
use App\Http\Controllers\Controller;

class GzipCompressionTest extends TestCase
{
    private function makeSolrResponse(array $docs): GuzzleResponse
    {
        $body = json_encode([
            'response' => [
                'numFound' => count($docs),
                'start' => 0,
                'docs' => $docs,
            ],
        ]);
        return new GuzzleResponse(200, [], $body);
    }

    private function streamContent(callable $callback): string
    {
        ob_start();
        $callback();
        return ob_get_clean();
    }

    public function test_response_without_accept_encoding_is_not_compressed()
    {
        $request = Request::create('/api/v1/records', 'GET');
        app()->instance('request', $request);

        $mockResponse = $this->makeSolrResponse([['id' => 'TST001', 'title' => 'Hello']]);
        $controller = new Controller();
        $streamResponse = $controller->responseFilter($mockResponse, 'json', 'application/json; charset=utf-8');

        $this->assertNull($streamResponse->headers->get('Content-Encoding'));

        $body = $this->streamContent(fn() => $streamResponse->sendContent());
        $this->assertJson($body);
        $this->assertStringContainsString('TST001', $body);
    }

    public function test_response_with_accept_encoding_gzip_sends_gzip_header()
    {
        $request = Request::create('/api/v1/records', 'GET', [], [], [], [
            'HTTP_ACCEPT_ENCODING' => 'gzip, deflate',
        ]);
        app()->instance('request', $request);

        $mockResponse = $this->makeSolrResponse([['id' => 'TST001', 'title' => 'Hello']]);
        $controller = new Controller();
        $streamResponse = $controller->responseFilter($mockResponse, 'json', 'application/json; charset=utf-8');

        $this->assertEquals('gzip', $streamResponse->headers->get('Content-Encoding'));
    }

    public function test_gzip_body_decodes_to_valid_json()
    {
        $request = Request::create('/api/v1/records', 'GET', [], [], [], [
            'HTTP_ACCEPT_ENCODING' => 'gzip',
        ]);
        app()->instance('request', $request);

        $docs = [['id' => 'TST001', 'title' => 'Doc One'], ['id' => 'TST002', 'title' => 'Doc Two']];
        $mockResponse = $this->makeSolrResponse($docs);
        $controller = new Controller();
        $streamResponse = $controller->responseFilter($mockResponse, 'json', 'application/json; charset=utf-8');

        $compressed = $this->streamContent(fn() => $streamResponse->sendContent());
        $this->assertNotEmpty($compressed);

        $decoded = gzdecode($compressed);
        $this->assertNotFalse($decoded, 'Body should be valid gzip data');
        $this->assertJson($decoded);

        $parsed = json_decode($decoded, true);
        $this->assertCount(2, $parsed);
        $this->assertEquals('TST001', $parsed[0]['id']);
        $this->assertEquals('TST002', $parsed[1]['id']);
    }

    public function test_gzip_body_decodes_to_valid_jsonl()
    {
        $request = Request::create('/api/v1/records', 'GET', [], [], [], [
            'HTTP_ACCEPT_ENCODING' => 'gzip',
        ]);
        app()->instance('request', $request);

        $mockResponse = $this->makeSolrResponse([['id' => 'TST003', 'title' => 'Doc JSONL']]);
        $controller = new Controller();
        $streamResponse = $controller->responseFilter($mockResponse, 'jsonl', 'application/jsonl; charset=utf-8');

        $compressed = $this->streamContent(fn() => $streamResponse->sendContent());
        $decoded = gzdecode($compressed);
        $this->assertNotFalse($decoded);

        $line = trim($decoded);
        $this->assertJson($line);
        $this->assertEquals('TST003', json_decode($line)->id);
    }

    public function test_gzip_compressed_body_is_smaller_than_uncompressed()
    {
        $docs = array_map(fn($i) => ['id' => "TST{$i}", 'title' => str_repeat('Lorem ipsum dolor sit amet ', 10)], range(1, 20));

        // Uncompressed
        $request = Request::create('/api/v1/records', 'GET');
        app()->instance('request', $request);
        $uncompressedResponse = (new Controller())->responseFilter($this->makeSolrResponse($docs), 'json', 'application/json; charset=utf-8');
        $uncompressedBody = $this->streamContent(fn() => $uncompressedResponse->sendContent());

        // Compressed
        $request = Request::create('/api/v1/records', 'GET', [], [], [], [
            'HTTP_ACCEPT_ENCODING' => 'gzip',
        ]);
        app()->instance('request', $request);
        $compressedResponse = (new Controller())->responseFilter($this->makeSolrResponse($docs), 'json', 'application/json; charset=utf-8');
        $compressedBody = $this->streamContent(fn() => $compressedResponse->sendContent());

        $this->assertLessThan(strlen($uncompressedBody), strlen($compressedBody));
    }
}
