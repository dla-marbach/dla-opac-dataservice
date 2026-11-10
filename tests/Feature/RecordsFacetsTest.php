<?php

namespace Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Tests\Concerns\InteractsWithSolr;
use Tests\TestCase;

class RecordsFacetsTest extends TestCase
{
    use InteractsWithSolr;

    /**
     * @param array<string, array<int, array<int, mixed>>> $facetFields
     */
    private function queueFacetResponse(array $facetFields, int $numFound): void
    {
        $this->solr->queue(new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
            'responseHeader' => ['status' => 0, 'QTime' => 1],
            'response' => ['numFound' => $numFound, 'start' => 0, 'docs' => []],
            'facet_counts' => ['facet_fields' => $facetFields],
        ], JSON_UNESCAPED_UNICODE)));
    }

    public function test_facets_return_value_counts_per_field(): void
    {
        $solr = $this->fakeSolr();
        $this->queueFacetResponse([
            'category' => [['Bestand', 1369], ['Kryptobestand', 2983]],
            'categorySub' => [['Nachlass', 800], [null, 12]],
        ], 4352);

        $this->get('/v1/records/facets?q=' . urlencode('id:BF*') . '&facet=category,categorySub&facetMissing=true')
            ->assertStatus(200)
            ->assertHeader('content-type', 'application/json; charset=utf-8')
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertExactJson([
                'documentCount' => 4352,
                'facets' => [
                    'category' => [
                        ['value' => 'Bestand', 'count' => 1369],
                        ['value' => 'Kryptobestand', 'count' => 2983],
                    ],
                    'categorySub' => [
                        ['value' => 'Nachlass', 'count' => 800],
                        ['value' => null, 'count' => 12],
                    ],
                ],
            ]);

        $parameters = $solr->queryParameters(0);
        $this->assertSame('id:BF*', $parameters['q']);
        $this->assertSame('0', $parameters['rows']);
        $this->assertSame('true', $parameters['facet']);
        $this->assertSame(['category', 'categorySub'], $parameters['facet.field']);
        $this->assertSame('100', $parameters['facet.limit']);
        $this->assertSame('0', $parameters['facet.offset']);
        $this->assertSame('1', $parameters['facet.mincount']);
        $this->assertSame('count', $parameters['facet.sort']);
        $this->assertSame('true', $parameters['facet.missing']);
        $this->assertSame('arrarr', $parameters['json.nl']);
        $this->assertArrayNotHasKey('facet.prefix', $parameters);
        $this->assertSame(1, $solr->requestCount());
    }

    public function test_facets_forward_filter_queries_and_options(): void
    {
        $solr = $this->fakeSolr();
        $this->queueFacetResponse(['gender' => [['weiblich', 275]]], 275);

        $join = '{!join from=personAt_id_mv to=id}id:BF* AND -parent_id_mv:*';

        $this->get('/v1/records/facets?q=' . urlencode('id:PE*') . '&fq=' . urlencode($join)
            . '&facet=gender&facetLimit=5&facetOffset=10&facetMinCount=0&facetSort=index&facetPrefix=w&sort=id+asc&fields=id')
            ->assertStatus(200)
            ->assertExactJson([
                'documentCount' => 275,
                'facets' => ['gender' => [['value' => 'weiblich', 'count' => 275]]],
            ]);

        $parameters = $solr->queryParameters(0);
        $this->assertSame([$join], (array) $parameters['fq']);
        $this->assertSame('gender', $parameters['facet.field']);
        $this->assertSame('5', $parameters['facet.limit']);
        $this->assertSame('10', $parameters['facet.offset']);
        $this->assertSame('0', $parameters['facet.mincount']);
        $this->assertSame('index', $parameters['facet.sort']);
        $this->assertSame('w', $parameters['facet.prefix']);
        $this->assertSame('false', $parameters['facet.missing']);
        $this->assertArrayNotHasKey('sort', $parameters);
        $this->assertArrayNotHasKey('fl', $parameters);
    }

    public function test_facets_without_query_use_all_documents(): void
    {
        $solr = $this->fakeSolr();
        $this->queueFacetResponse(['filterSource' => []], 0);

        $this->get('/v1/records/facets?facet=filterSource')
            ->assertStatus(200)
            ->assertExactJson(['documentCount' => 0, 'facets' => ['filterSource' => []]]);

        $this->assertSame('*:*', $solr->queryParameters(0)['q']);
    }

    /**
     * @dataProvider invalidParameterProvider
     */
    public function test_facets_reject_invalid_parameters(string $queryString): void
    {
        $solr = $this->fakeSolr();

        $this->get('/v1/records/facets?' . $queryString)
            ->assertStatus(400)
            ->assertHeader('content-type', 'application/json; charset=utf-8')
            ->assertJsonStructure(['error']);

        $this->assertSame(0, $solr->requestCount());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function invalidParameterProvider(): array
    {
        return [
            'missing facet' => ['q=Marbach'],
            'empty facet' => ['facet=,'],
            'invalid field name' => ['facet=' . urlencode('category}&rows=10')],
            'internal field' => ['facet=_version_'],
            'export field' => ['facet=exportMODS'],
            'limit too small' => ['facet=category&facetLimit=0'],
            'limit too large' => ['facet=category&facetLimit=10001'],
            'limit not numeric' => ['facet=category&facetLimit=all'],
            'negative offset' => ['facet=category&facetOffset=-1'],
            'negative mincount' => ['facet=category&facetMinCount=-1'],
            'invalid sort' => ['facet=category&facetSort=desc'],
            'invalid missing' => ['facet=category&facetMissing=maybe'],
        ];
    }
}
