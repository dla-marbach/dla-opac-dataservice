<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithSolr;
use Tests\TestCase;

class RecordsFormatTest extends TestCase
{
    use InteractsWithSolr;

    public function test_jsonl_returns_one_document_per_line(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueFieldList('id');
        $solr->queueDocuments([['id' => 'DLA0000001']]);
        $solr->queueDocuments([['id' => 'DLA0000001'], ['id' => 'DLA0000002']], 2);

        $response = $this->get('/v1/records?q=Marbach&format=jsonl');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/jsonl; charset=utf-8');

        $lines = array_values(array_filter(explode(PHP_EOL, $response->streamedContent())));

        $this->assertCount(2, $lines);
        $this->assertSame(['id' => 'DLA0000001'], json_decode($lines[0], true));
        $this->assertSame('json', $solr->queryParameters(2)['wt']);
    }

    public function test_csv_is_streamed_unchanged_from_solr(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueFieldList('id,display');
        $solr->queueDocuments([['id' => 'DLA0000001']]);
        $solr->queueRaw("id,display\nDLA0000001,Marbach\n", 'text/plain');

        $response = $this->get('/v1/records?q=Marbach&format=csv');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json; charset=utf-8');

        $this->assertSame("id,display\nDLA0000001,Marbach\n", $response->streamedContent());
        $this->assertSame('csv', $solr->queryParameters(2)['wt']);
        $this->assertSame('id,display', $solr->queryParameters(2)['fl']);
    }

    public function test_tsv_uses_tab_separators(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueFieldList('id,display');
        $solr->queueDocuments([['id' => 'DLA0000001']]);
        $solr->queueRaw("id\tdisplay\nDLA0000001\tMarbach\n");

        $response = $this->get('/v1/records?q=Marbach&format=tsv');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'text/plain; charset=utf-8');

        $parameters = $solr->queryParameters(2);
        $this->assertSame('csv', $parameters['wt']);
        $this->assertSame("\t", $parameters['csv.separator']);
        $this->assertSame("\n", $parameters['csv.mv.separator']);
        $this->assertSame("id\tdisplay\nDLA0000001\tMarbach\n", $response->streamedContent());
    }

    public function test_tsv_light_requests_a_reduced_field_list(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueDocuments([['id' => 'DLA0000001']]);
        $solr->queueRaw("id\tdisplay\nDLA0000001\tMarbach\n");

        $this->get('/v1/records?q=Marbach&format=tsv-light')
            ->assertStatus(200)
            ->assertHeader('content-type', 'text/plain; charset=utf-8');

        $fields = explode(',', $solr->queryParameters(1)['fl']);
        $this->assertContains('display', $fields);
        $this->assertContains('url', $fields);
        $this->assertNotContains('exportMODS', $fields);
        $this->assertSame(2, $solr->requestCount(), 'tsv-light must not ask Solr for the default field list');
    }

    public function test_mods_wraps_documents_in_a_mods_collection(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueDocuments([$this->exampleDocument()]);
        $solr->queueDocuments([$this->exampleDocument(), $this->exampleDocument('DLA0000002')], 2);

        $response = $this->get('/v1/records?q=Marbach&format=mods');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'text/xml; charset=utf-8');

        $body = $response->streamedContent();

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $body);
        $this->assertStringContainsString('<modsCollection', $body);
        $this->assertStringEndsWith('</modsCollection>', $body);
        $this->assertSame(2, substr_count($body, '<mods>'), 'namespaces of single records must be stripped');
        $this->assertNotNull(simplexml_load_string($body));
        $this->assertSame('exportMODS', $solr->queryParameters(1)['fl']);
    }

    public function test_dublin_core_wraps_documents_in_a_records_element(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueDocuments([$this->exampleDocument()]);
        $solr->queueDocuments([$this->exampleDocument()]);

        $response = $this->get('/v1/records?q=Marbach&format=dc');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'text/xml; charset=utf-8');

        $body = $response->streamedContent();

        $this->assertStringContainsString('<records', $body);
        $this->assertStringContainsString('<oai_dc:dc>', $body);
        $this->assertStringEndsWith('</records>', $body);
        $this->assertNotNull(simplexml_load_string($body));
        $this->assertSame('exportDC', $solr->queryParameters(1)['fl']);
    }

    public function test_ris_returns_plain_text_records(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueDocuments([$this->exampleDocument()]);
        $solr->queueDocuments([$this->exampleDocument(), $this->exampleDocument('DLA0000002')], 2);

        $response = $this->get('/v1/records?q=Marbach&format=ris');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'text/plain; charset=utf-8');

        $body = $response->streamedContent();

        $this->assertSame(2, substr_count($body, 'TY  - BOOK'));
        $this->assertSame(2, substr_count($body, 'ER  - '));
        $this->assertSame('exportRIS', $solr->queryParameters(1)['fl']);
    }

    public function test_unknown_format_falls_back_to_json(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueDocuments([['id' => 'DLA0000001']]);
        $solr->queueDocuments([['id' => 'DLA0000001']]);

        $response = $this->get('/v1/records?q=Marbach&format=xml');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json; charset=utf-8');

        $this->assertSame('json', $solr->queryParameters(1)['wt']);
    }

    public function test_response_is_gzip_compressed_when_the_client_accepts_it(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueFieldList('id');
        $solr->queueDocuments([['id' => 'DLA0000001']]);
        $solr->queueDocuments([['id' => 'DLA0000001']]);

        $response = $this->withHeaders(['Accept-Encoding' => 'gzip'])->get('/v1/records?q=Marbach');

        $response->assertStatus(200)
            ->assertHeader('Content-Encoding', 'gzip');

        $decoded = gzdecode($response->streamedContent());

        $this->assertNotFalse($decoded);
        $this->assertSame([['id' => 'DLA0000001']], json_decode((string) $decoded, true));
    }

    public function test_umlauts_are_not_escaped_in_json_output(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueFieldList('id, display');
        $solr->queueDocuments([['id' => 'DLA0000001', 'display' => 'Kästner, Erich']]);
        $solr->queueDocuments([['id' => 'DLA0000001', 'display' => 'Kästner, Erich']]);

        $response = $this->get('/v1/records?q=' . urlencode('Kästner'));

        $this->assertStringContainsString('Kästner, Erich', $response->streamedContent());
    }
}
