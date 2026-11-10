<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>DLA Datendienst</title>

    {{-- Assets aus dem Composer-Paket swagger-api/swagger-ui, kopiert beim composer install --}}
    <link href="{{ asset('vendor/swagger-ui/swagger-ui.css') }}" rel="stylesheet">

    <style>
        html {
            box-sizing: border-box;
        }

        *, *:before, *:after {
            box-sizing: inherit;
        }

        body {
            margin: 0;
            background: #fafafa;
        }
    </style>
</head>
<body>
<div id="swagger-ui"></div>

<script src="{{ asset('vendor/swagger-ui/swagger-ui-bundle.js') }}"></script>

<script>
    window.onload = function () {
        window.ui = SwaggerUIBundle({
            url: '{{ route('swagger-openapi-json', [], false) }}',
            dom_id: '#swagger-ui',
            deepLinking: true,
            tryItOutEnabled: true,
            presets: [
                SwaggerUIBundle.presets.apis,
            ],
            layout: 'BaseLayout',
        });

        ui.initOAuth({
            clientId: '{{ config('swagger-ui.oauth.client_id') }}',
            clientSecret: '{{ config('swagger-ui.oauth.client_secret') }}',
        });
    };
</script>
</body>
</html>
