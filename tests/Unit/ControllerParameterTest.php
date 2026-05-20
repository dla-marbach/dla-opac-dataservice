<?php

namespace Tests\Unit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tests\TestCase;

class ControllerParameterTest extends TestCase
{
    public function test_transform_given_parameter_maps_size_to_rows(): void
    {
        $request = Request::create('/api/v1/records', 'GET', ['size' => 5]);

        $result = (new Controller())->transformGivenParameter($request);

        $this->assertSame(5, $result['query']['rows']);
    }

    public function test_transform_given_parameter_maps_from_to_start_minus_one(): void
    {
        $request = Request::create('/api/v1/records', 'GET', ['from' => 2]);

        $result = (new Controller())->transformGivenParameter($request);

        $this->assertSame(1, $result['query']['start']);
    }

    public function test_transform_given_parameter_sets_default_rows_without_parameters(): void
    {
        $request = Request::create('/api/v1/records', 'GET');

        $result = (new Controller())->transformGivenParameter($request);

        $this->assertSame(10000000, $result['query']['rows']);
    }

    public function test_transform_given_parameter_sets_query_parameter(): void
    {
        $request = Request::create('/api/v1/records', 'GET', ['q' => 'test']);

        $result = (new Controller())->transformGivenParameter($request);

        $this->assertSame('test', $result['query']['q']);
    }

    public function test_transform_given_parameter_maps_fields_to_fl(): void
    {
        $request = Request::create('/api/v1/records', 'GET', ['fields' => 'title']);

        $result = (new Controller())->transformGivenParameter($request);

        $this->assertSame('title', $result['query']['fl']);
    }
}
