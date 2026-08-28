<?php

namespace App\Http\Controllers;

use App\Support\SolrClientFactory;
use NextApps\SwaggerUi\Http\Controllers\OpenApiJsonController;

class OverrideOpenApiJsonController extends OpenApiJsonController
{
    protected function configureServer(array $json) : array
    {
        // get field information dynamically
        $client = app(SolrClientFactory::class)->coreClient('config/');
        $response = $client->request('GET', 'requestHandler', ['query' => ['componentName' => '/select']]);
        $responseBody = $response->getBody();
        $jsonResponse = json_decode($responseBody->getContents());
        $select = '/select';
        $flArray = explode(', ', $jsonResponse->config->requestHandler->{$select}->defaults->fl);

        $json['components']['parameters']['fields']['examples']['all']['value'] = $flArray;

        if (! config('swagger-ui.modify_file')) {
            return $json;
        }

        $json['servers'] = [
            ['url' => config('app.url')],
        ];

        return $json;
    }
}
