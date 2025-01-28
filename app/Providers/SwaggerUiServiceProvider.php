<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class SwaggerUiServiceProvider extends ServiceProvider
{
    public function boot() : void
    {
        // override method in OpenApiJsonController to load field names dynamically
        $this->app->bind('NextApps\SwaggerUi\Http\Controllers\OpenApiJsonController', 'App\Http\Controllers\OverrideOpenApiJsonController');
    }
}
