<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithSolr;
use Tests\TestCase;

/**
 * Exercises the endpoints with the examples that are documented in
 * `resources/swagger/openapi.json` so that documentation and implementation
 * cannot drift apart unnoticed.
 */
class OpenApiExamplesTest extends TestCase
{
    use InteractsWithSolr;

    /**
     * @var array<string, mixed>|null
     */
    private static ?array $specification = null;

    /**
     * @return array<string, mixed>
     */
    private static function specification(): array
    {
        if (self::$specification === null) {
            $contents = file_get_contents(__DIR__ . '/../../resources/swagger/openapi.json');
            self::$specification = json_decode((string) $contents, true, 512, JSON_THROW_ON_ERROR);
        }

        return self::$specification;
    }

    /**
     * @return array<int, mixed>
     */
    private static function parameterExamples(string $parameter): array
    {
        $examples = self::specification()['components']['parameters'][$parameter]['examples'] ?? [];

        $values = [];
        foreach ($examples as $name => $example) {
            $value = $example['value'] ?? null;
            if ($value === null || $value === '' || $value === [] || $value === ['']) {
                continue;
            }
            $values[$name] = $value;
        }

        return $values;
    }

    public function test_specification_is_valid_json_and_documents_all_routes(): void
    {
        $paths = array_keys(self::specification()['paths']);

        $this->assertSame([
            '/records',
            '/records/count',
            '/record/{id}.{format}',
            '/collections',
            '/collection/{id}.{format}',
            '/info',
            '/info/schema',
        ], $paths);
    }

    /**
     * @dataProvider queryExampleProvider
     */
    public function test_documented_query_examples_are_forwarded_to_solr(string $query, string $expected): void
    {
        $solr = $this->fakeSolr();
        $solr->queueFieldList();
        $solr->queueDocuments([$this->exampleDocument()]);
        $solr->queueDocuments([$this->exampleDocument()]);

        $this->get('/v1/records?q=' . urlencode($query))->assertStatus(200);

        $this->assertSame($expected, $solr->queryParameters(1)['q']);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function queryExampleProvider(): array
    {
        $cases = [];
        foreach (self::parameterExamples('query') as $name => $value) {
            $cases[$name] = [(string) $value, (string) $value];
        }

        // The documented range shortcut is rewritten into native Solr syntax.
        $cases['range'] = ['dateOrigin:("RANGE 1982 TO 1983")', 'dateOrigin:[1982 TO 1983]'];

        return $cases;
    }

    /**
     * @dataProvider filterQueryExampleProvider
     *
     * @param array<int, string> $filters
     */
    public function test_documented_filter_query_examples_are_forwarded_to_solr(array $filters): void
    {
        $solr = $this->fakeSolr();
        $solr->queueFieldList();
        $solr->queueDocuments([$this->exampleDocument()]);
        $solr->queueDocuments([$this->exampleDocument()]);

        $query = http_build_query(['q' => '*:*']);
        foreach ($filters as $filter) {
            $query .= '&fq=' . urlencode($filter);
        }

        $this->get('/v1/records?' . $query)->assertStatus(200);

        $this->assertSame($filters, (array) $solr->queryParameters(2)['fq']);
    }

    /**
     * @return array<string, array<int, array<int, string>>>
     */
    public function filterQueryExampleProvider(): array
    {
        $cases = [];
        foreach (self::parameterExamples('fq') as $name => $value) {
            $cases[$name] = [array_values((array) $value)];
        }

        return $cases;
    }

    /**
     * @dataProvider sortExampleProvider
     */
    public function test_documented_sort_examples_are_forwarded_to_solr(string $sort): void
    {
        $solr = $this->fakeSolr();
        $solr->queueFieldList();
        $solr->queueDocuments([$this->exampleDocument()]);
        $solr->queueDocuments([$this->exampleDocument()]);

        $this->get('/v1/records?q=*:*&sort=' . urlencode($sort))->assertStatus(200);

        $this->assertSame($sort, $solr->queryParameters(2)['sort']);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function sortExampleProvider(): array
    {
        $cases = [];
        foreach (self::parameterExamples('sort') as $name => $value) {
            $cases[$name] = [implode(',', (array) $value)];
        }

        return $cases;
    }

    /**
     * @dataProvider fieldsExampleProvider
     */
    public function test_documented_field_examples_are_forwarded_to_solr(string $fields): void
    {
        $solr = $this->fakeSolr();
        $solr->queueDocuments([$this->exampleDocument()]);
        $solr->queueDocuments([$this->exampleDocument()]);

        $this->get('/v1/records?q=*:*&fields=' . urlencode($fields))->assertStatus(200);

        $this->assertSame($fields, $solr->queryParameters(1)['fl']);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function fieldsExampleProvider(): array
    {
        $cases = [];
        foreach (self::parameterExamples('fields') as $name => $value) {
            $fields = implode(',', (array) $value);
            if ($fields === '' || $fields === '*') {
                continue;
            }
            $cases[$name] = [$fields];
        }

        return $cases;
    }

    /**
     * @dataProvider documentedFormatProvider
     */
    public function test_documented_formats_are_accepted_by_records_endpoint(string $format): void
    {
        $solr = $this->fakeSolr();

        if (in_array($format, ['json', 'jsonl', 'csv', 'tsv'], true)) {
            $solr->queueFieldList();
        }

        $solr->queueDocuments([$this->exampleDocument()]);

        if (in_array($format, ['csv', 'tsv', 'tsv-light'], true)) {
            $solr->queueRaw("id\nDLA0000001\n");
        } else {
            $solr->queueDocuments([$this->exampleDocument()]);
        }

        $this->get('/v1/records?q=*:*&format=' . $format)->assertStatus(200);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function documentedFormatProvider(): array
    {
        $formats = self::specification()['components']['parameters']['formatInQuery']['schema']['enum'] ?? [];

        $cases = [];
        foreach ($formats as $format) {
            $cases[$format] = [$format];
        }

        return $cases;
    }
}
