<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithSolr;
use Tests\TestCase;

class RecordsCountTest extends TestCase
{
    use InteractsWithSolr;

    public function test_count_returns_number_of_documents(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueDocuments([], 123);

        $this->get('/v1/records/count?q=Marbach')
            ->assertStatus(200)
            ->assertHeader('content-type', 'application/json; charset=utf-8')
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertExactJson(['documentCount' => 123]);

        $parameters = $solr->queryParameters(0);
        $this->assertSame('Marbach', $parameters['q']);
        $this->assertSame('0', $parameters['rows']);
        $this->assertSame(1, $solr->requestCount());
    }

    public function test_count_without_query_counts_all_documents(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueDocuments([], 9);

        $this->get('/v1/records/count')->assertExactJson(['documentCount' => 9]);

        $this->assertSame('*:*', $solr->queryParameters(0)['q']);
    }

    public function test_count_forwards_filter_queries(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueDocuments([], 3);

        $this->get('/v1/records/count?fq=filterDigital:true&fq=' . urlencode('filterMedium_mv:Buch'))
            ->assertExactJson(['documentCount' => 3]);

        $this->assertSame(
            ['filterDigital:true', 'filterMedium_mv:Buch'],
            (array) $solr->queryParameters(0)['fq']
        );
    }

    /**
     * @dataProvider exportFormatProvider
     */
    public function test_count_restricts_export_formats_to_documents_with_export_field(string $format, string $field): void
    {
        $solr = $this->fakeSolr();
        $solr->queueDocuments([], 5);

        $this->get('/v1/records/count?q=Marbach&format=' . $format)
            ->assertExactJson(['documentCount' => 5]);

        $this->assertSame('Marbach AND ' . $field . ':*', $solr->queryParameters(0)['q']);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function exportFormatProvider(): array
    {
        return [
            'ris' => ['ris', 'exportRIS'],
            'mods' => ['mods', 'exportMODS'],
            'dc' => ['dc', 'exportDC'],
        ];
    }

    public function test_count_with_json_format_does_not_change_the_query(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueDocuments([], 5);

        $this->get('/v1/records/count?q=Marbach&format=json')
            ->assertExactJson(['documentCount' => 5]);

        $this->assertSame('Marbach', $solr->queryParameters(0)['q']);
    }
}
