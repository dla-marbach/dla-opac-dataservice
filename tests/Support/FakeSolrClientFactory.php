<?php

namespace Tests\Support;

use App\Support\SolrClientFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use OutOfBoundsException;

/**
 * Solr client factory that never talks to a real Solr instance.
 *
 * Responses are queued in the order in which the application performs its
 * requests; every request is recorded so tests can assert on the generated
 * Solr query parameters.
 */
class FakeSolrClientFactory extends SolrClientFactory
{
    /** @var array<int, array<string, mixed>> */
    public array $transactions = [];

    /** @var array<int, string> */
    public array $baseUris = [];

    private MockHandler $mockHandler;

    private HandlerStack $handlerStack;

    public function __construct()
    {
        $this->mockHandler = new MockHandler();
        $this->handlerStack = HandlerStack::create($this->mockHandler);
        $this->handlerStack->push(Middleware::history($this->transactions));
    }

    public function make(string $baseUri): Client
    {
        $this->baseUris[] = $baseUri;

        return new Client(['base_uri' => $baseUri, 'handler' => $this->handlerStack]);
    }

    /**
     * Queue one or more responses that are returned in order.
     */
    public function queue(Response ...$responses): self
    {
        foreach ($responses as $response) {
            $this->mockHandler->append($response);
        }

        return $this;
    }

    /**
     * Queue a Solr JSON response for the given documents.
     *
     * @param array<int, array<string, mixed>> $docs
     */
    public function queueDocuments(array $docs, ?int $numFound = null): self
    {
        return $this->queue(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
            'responseHeader' => ['status' => 0, 'QTime' => 1],
            'response' => [
                'numFound' => $numFound ?? count($docs),
                'start' => 0,
                'docs' => $docs,
            ],
        ], JSON_UNESCAPED_UNICODE)));
    }

    /**
     * Queue the response of Solr's `/config/requestHandler` endpoint which is
     * used to determine the default field list.
     */
    public function queueFieldList(string $fieldList = 'id, title, display'): self
    {
        return $this->queue(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
            'config' => [
                'requestHandler' => [
                    '/select' => ['defaults' => ['fl' => $fieldList]],
                ],
            ],
        ])));
    }

    /**
     * Queue a plain text (CSV/TSV) Solr response.
     */
    public function queueRaw(string $body, string $contentType = 'text/plain'): self
    {
        return $this->queue(new Response(200, ['Content-Type' => $contentType], $body));
    }

    /**
     * Number of requests that have been sent to Solr.
     */
    public function requestCount(): int
    {
        return count($this->transactions);
    }

    /**
     * Query parameters of the n-th request (0 based).
     *
     * @return array<string, string|array<int, string>>
     */
    public function queryParameters(int $index = 0): array
    {
        $parameters = [];

        foreach (explode('&', (string) parse_url($this->requestUri($index), PHP_URL_QUERY)) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key = urldecode($key);
            $value = urldecode($value);

            if (array_key_exists($key, $parameters)) {
                $parameters[$key] = array_merge((array) $parameters[$key], [$value]);
                continue;
            }

            $parameters[$key] = $value;
        }

        return $parameters;
    }

    public function requestUri(int $index = 0): string
    {
        if (! isset($this->transactions[$index])) {
            throw new OutOfBoundsException("No Solr request recorded at index {$index}.");
        }

        return (string) $this->transactions[$index]['request']->getUri();
    }
}
