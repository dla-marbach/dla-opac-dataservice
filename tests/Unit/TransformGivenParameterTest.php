<?php

namespace Tests\Unit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tests\TestCase;

class TransformGivenParameterTest extends TestCase
{
    private function transform(string $queryString): array
    {
        $request = Request::create('/v1/records?' . $queryString, 'GET');
        $request->server->set('QUERY_STRING', $queryString);

        return (new Controller())->transformGivenParameter($request);
    }

    public function test_default_row_limit_is_used_without_size(): void
    {
        $result = $this->transform('q=Marbach');

        $this->assertSame(10000000, $result['query']['rows']);
        $this->assertArrayNotHasKey('start', $result['query']);
    }

    public function test_size_is_mapped_to_rows(): void
    {
        $this->assertSame(25, $this->transform('size=25')['query']['rows']);
    }

    public function test_from_is_mapped_to_zero_based_start(): void
    {
        $this->assertSame(9, $this->transform('from=10')['query']['start']);
    }

    public function test_from_below_one_does_not_produce_a_negative_start(): void
    {
        $this->assertSame(0, $this->transform('from=-5')['query']['start']);
    }

    public function test_sort_is_passed_through_unchanged(): void
    {
        $this->assertSame(
            'dateOrigin asc,score desc',
            $this->transform('sort=' . urlencode('dateOrigin asc,score desc'))['query']['sort']
        );
    }

    public function test_fields_are_mapped_to_field_list(): void
    {
        $this->assertSame('id,display', $this->transform('fields=' . urlencode('id,display'))['query']['fl']);
    }

    public function test_wildcard_fields_do_not_set_a_field_list(): void
    {
        $this->assertArrayNotHasKey('fl', $this->transform('fields=*')['query']);
    }

    public function test_range_shortcut_is_rewritten_to_solr_range_syntax(): void
    {
        $result = $this->transform('q=' . urlencode('dateOrigin:("RANGE 1982 TO 1983")'));

        $this->assertSame('dateOrigin:[1982 TO 1983]', $result['query']['q']);
    }

    public function test_repeated_filter_queries_are_collected(): void
    {
        $result = $this->transform('fq=' . urlencode('filterDigital:true') . '&fq=' . urlencode('filterType_mv:Audio'));

        $this->assertSame(['filterDigital:true', 'filterType_mv:Audio'], $result['query']['fq']);
    }

    public function test_array_style_filter_queries_are_collected(): void
    {
        $result = $this->transform('fq[]=' . urlencode('filterDigital:true') . '&fq[]=' . urlencode('filterMedium_mv:Buch'));

        $this->assertSame(['filterDigital:true', 'filterMedium_mv:Buch'], $result['query']['fq']);
    }

    public function test_empty_filter_queries_are_ignored(): void
    {
        $this->assertArrayNotHasKey('fq', $this->transform('fq=&q=Marbach')['query']);
    }

    public function test_request_without_parameters_only_sets_the_row_limit(): void
    {
        $this->assertSame(['rows' => 10000000], $this->transform('')['query']);
    }
}
