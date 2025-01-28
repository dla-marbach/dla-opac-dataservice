<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client as Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use NextApps\SwaggerUi\Http\Controllers\OpenApiJsonController;
use RuntimeException;

class OverrideOpenApiJsonController extends OpenApiJsonController
{
    protected function configureServer(array $json) : array
    {
        // get field information dynamically
        $client = new Client(['base_uri' => config('dla_solr.base_uri') . config('dla_solr.core') . '/' . 'config/']);
        $response = $client->request('GET', 'requestHandler', ['componentName' => '/select']);
        $responseBody = $response->getBody();
        $jsonResponse = json_decode($responseBody->getContents());
        $select = '/select';
        $flArray = explode(', ', $jsonResponse->config->requestHandler->{$select}->defaults->fl);

        $json['components']['parameters']['fields']['schema']['items']['enum'] = $flArray;

        if (! config('swagger-ui.modify_file')) {
            return $json;
        }

        $json['servers'] = [
            ['url' => config('app.url')],
        ];

        return $json;
    }
}
