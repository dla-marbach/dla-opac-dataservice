<?php

namespace Tests\Feature;

use Tests\Concerns\InteractsWithSolr;
use Tests\TestCase;

class SwaggerUiTest extends TestCase
{
    use InteractsWithSolr;

    public function test_swagger_ui_is_served_on_the_root_path(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('swagger-ui', false);
    }

    public function test_openapi_json_contains_the_field_names_provided_by_solr(): void
    {
        $solr = $this->fakeSolr();
        $solr->queueFieldList('id, display, dateOrigin');

        $response = $this->get('/openapi.json');

        $response->assertStatus(200);

        $specification = $response->json();

        $this->assertSame(
            ['id', 'display', 'dateOrigin'],
            $specification['components']['parameters']['fields']['examples']['all']['value']
        );
        $this->assertArrayHasKey('/records', $specification['paths']);
        $this->assertStringContainsString('testcore/config/requestHandler', $solr->requestUri(0));
    }
}
