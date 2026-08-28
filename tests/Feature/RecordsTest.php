<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithSolr;
use Tests\TestCase;

class RecordsTest extends TestCase
{
    use InteractsWithSolr;

    public function test_records_returns_json_array_of_documents(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueFieldList('id, display');
        $solr->queueDocuments([$this->exampleDocument()]);
        $solr->queueDocuments([$this->exampleDocument('DLA0000001'), $this->exampleDocument('DLA0000002')], 2);

        $response = $this->get('/v1/records?q=Marbach');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json; charset=utf-8')
            ->assertHeader('Access-Control-Allow-Origin', '*');

        $documents = json_decode($response->streamedContent(), true);

        $this->assertIsArray($documents);
        $this->assertCount(2, $documents);
        $this->assertSame('DLA0000001', $documents[0]['id']);
        $this->assertSame('DLA0000002', $documents[1]['id']);
    }

    public function test_records_without_query_defaults_to_all_documents(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueFieldList();
        $solr->queueDocuments([$this->exampleDocument()]);
        $solr->queueDocuments([$this->exampleDocument()]);

        $this->get('/v1/records')->assertStatus(200);

        $countParameters = $solr->queryParameters(1);
        $this->assertSame('*:*', $countParameters['q']);
        $this->assertSame('1', $countParameters['rows']);

        $exportParameters = $solr->queryParameters(2);
        $this->assertSame('*:*', $exportParameters['q']);
        $this->assertSame('10000000', $exportParameters['rows']);
        $this->assertSame('json', $exportParameters['wt']);
    }

    public function test_records_forwards_pagination_sorting_and_field_selection(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueDocuments([$this->exampleDocument()]);
        $solr->queueDocuments([$this->exampleDocument()]);

        $this->get('/v1/records?q=Marbach&from=11&size=25&sort=' . urlencode('dateOrigin asc,score desc') . '&fields=' . urlencode('id,display'))
            ->assertStatus(200);

        $parameters = $solr->queryParameters(1);
        $this->assertSame('Marbach', $parameters['q']);
        $this->assertSame('25', $parameters['rows']);
        $this->assertSame('10', $parameters['start']);
        $this->assertSame('dateOrigin asc,score desc', $parameters['sort']);
        $this->assertSame('id,display', $parameters['fl']);
    }

    public function test_records_forwards_multiple_filter_queries(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueFieldList();
        $solr->queueDocuments([$this->exampleDocument()]);
        $solr->queueDocuments([$this->exampleDocument()]);

        $this->get('/v1/records?q=Marbach&fq=filterDigital:true&fq=' . urlencode('filterType_mv:Audio'))
            ->assertStatus(200);

        $this->assertSame(
            ['filterDigital:true', 'filterType_mv:Audio'],
            (array) $solr->queryParameters(2)['fq']
        );
    }

    public function test_records_rewrites_range_shortcut_into_solr_syntax(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueFieldList();
        $solr->queueDocuments([$this->exampleDocument()]);
        $solr->queueDocuments([$this->exampleDocument()]);

        $this->get('/v1/records?q=' . urlencode('dateOrigin:("RANGE 1982 TO 1983")'))
            ->assertStatus(200);

        $this->assertSame('dateOrigin:[1982 TO 1983]', $solr->queryParameters(1)['q']);
    }

    public function test_records_uses_default_field_list_only_when_no_fields_are_given(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueFieldList('id, display, dateOrigin');
        $solr->queueDocuments([$this->exampleDocument()]);
        $solr->queueDocuments([$this->exampleDocument()]);

        $this->get('/v1/records?q=Marbach')->assertStatus(200);

        $this->assertSame('id, display, dateOrigin', $solr->queryParameters(2)['fl']);
    }

    public function test_records_with_wildcard_fields_does_not_restrict_the_field_list(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueFieldList('id, display');
        $solr->queueDocuments([$this->exampleDocument()]);
        $solr->queueDocuments([$this->exampleDocument()]);

        $this->get('/v1/records?q=Marbach&fields=*')->assertStatus(200);

        $this->assertSame('id, display', $solr->queryParameters(2)['fl']);
    }

    public function test_records_returns_no_content_when_solr_has_no_matches(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueFieldList();
        $solr->queueDocuments([], 0);

        $this->get('/v1/records?q=doesnotexist')
            ->assertStatus(204)
            ->assertHeader('Access-Control-Allow-Origin', '*');

        $this->assertSame(2, $solr->requestCount());
    }
}
