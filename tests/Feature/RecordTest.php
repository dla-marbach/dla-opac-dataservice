<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithSolr;
use Tests\TestCase;

class RecordTest extends TestCase
{
    use InteractsWithSolr;

    public function test_record_queries_solr_by_id(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueFieldList('id, display');
        $solr->queueDocuments([$this->exampleDocument()]);
        $solr->queueDocuments([$this->exampleDocument()]);

        $response = $this->get('/v1/record/DLA0000001');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json; charset=utf-8');

        $this->assertSame('id:DLA0000001', $solr->queryParameters(1)['q']);

        $documents = json_decode($response->streamedContent(), true);
        $this->assertSame('DLA0000001', $documents[0]['id']);
    }

    /**
     * @dataProvider formatProvider
     */
    public function test_record_supports_all_documented_formats(string $format, string $contentType): void
    {
        $solr = $this->fakeSolr();

        if (in_array($format, ['json', 'jsonl', 'csv', 'tsv'], true)) {
            $solr->queueFieldList('id, display');
        }

        $solr->queueDocuments([$this->exampleDocument()]);

        if (in_array($format, ['csv', 'tsv', 'tsv-light'], true)) {
            $solr->queueRaw("id\nDLA0000001\n");
        } else {
            $solr->queueDocuments([$this->exampleDocument()]);
        }

        $this->get('/v1/record/DLA0000001.' . $format)
            ->assertStatus(200)
            ->assertHeader('content-type', $contentType);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function formatProvider(): array
    {
        return [
            'json' => ['json', 'application/json; charset=utf-8'],
            'jsonl' => ['jsonl', 'application/jsonl; charset=utf-8'],
            'tsv' => ['tsv', 'text/plain; charset=utf-8'],
            'tsv-light' => ['tsv-light', 'text/plain; charset=utf-8'],
            'ris' => ['ris', 'text/plain; charset=utf-8'],
            'mods' => ['mods', 'text/xml; charset=utf-8'],
            'dc' => ['dc', 'text/xml; charset=utf-8'],
        ];
    }

    public function test_record_returns_no_content_for_unknown_id(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueFieldList();
        $solr->queueDocuments([], 0);

        $this->get('/v1/record/DLA9999999')->assertStatus(204);
    }

    public function test_record_with_invalid_id_is_not_routed_to_solr(): void
    {
        $solr = $this->fakeSolr();

        $this->get('/v1/record/invalid-id')->assertStatus(404);

        $this->assertSame(0, $solr->requestCount());
    }

    public function test_record_forwards_field_selection(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueDocuments([$this->exampleDocument()]);
        $solr->queueDocuments([$this->exampleDocument()]);

        $this->get('/v1/record/DLA0000001.json?fields=' . urlencode('id,display'))
            ->assertStatus(200);

        $this->assertSame('id,display', $solr->queryParameters(0)['fl']);
    }
}
