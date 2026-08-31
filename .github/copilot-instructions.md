# DLA Datendienst Copilot Guide
## Architecture
- Laravel 8 app in `app/Http/Controllers` exposes every public endpoint in `routes/api.php` (all prefixed with `/v1`); extend the API by adding controller methods there rather than scattering Solr calls elsewhere.
- Solr access is centralized in `App\Support\SolrClientFactory`, which builds the Guzzle clients from `config/dla_solr.php`; always create clients through that factory so tests can replace them.
- `transformGivenParameter()` converts user params into Solr syntax (sort, rows, start, fields); when adding filters, extend this helper so pagination and validation stay uniform.
- Streaming happens in `responseFilter()` plus `formattingResponse()`, which read Solr chunks directly (json-machine) and answer `204 No Content` on empty results; new export types must plug into this pipeline to preserve memory-friendly downloads.
- `responseFilter()` normalizes namespaces of the `exportMODS`/`exportDC` fields and wraps them in a collection element; mirror that approach for any future `exportXYZ` fields returned by Solr.
- Collections are data-driven via `config/dla_collection.php`; `/v1/collection/{id}` redirects to `/v1/records` with the stored query, so keep IDs numeric and queries Solr-safe.
## Documentation & Swagger
- Swagger UI routes come from `nextapps/laravel-swagger-ui`; `SwaggerUiServiceProvider` binds its controller to `OverrideOpenApiJsonController`, which hits Solr's `/config/requestHandler` endpoint to populate the `fields` enum dynamically before returning the spec.
- The spec path is configured via `config/swagger-ui.php` (defaults to `resources/swagger/openapi.json`)
- Customized Swagger assets live under `resources/views/vendor/swagger-ui` and are served directly from `public/css/swagger-ui.css` and `public/js/swagger-ui-bundle.js`; there is no JS/CSS build step.
## Environment & Configuration
- Required env vars: `DLA_SOLR_BASE_URI` (with trailing slash), `DLA_SOLR_BASE_CORE`, and `APP_URL` for Swagger server metadata
- `config('dla_collection')` entries expose public URLs inside API responses; keep `info`, `query`, and `url` in sync when curating new sub-collections.
- Example notebooks in `examples/` hit the deployed API; treat them as integration smoke tests when changing query syntax or response formats.
## Developer Workflows
- Install PHP deps with `composer install`, copy `.env`, set Solr variables, then run `php artisan key:generate`.
- Serve locally via `php artisan serve`; endpoints live under `http://127.0.0.1:8000/v1` and proxy whatever Solr instance you configured.
- Run the test suite with `vendor/bin/phpunit`; it never needs a Solr instance.
- Feature tests belong in `tests/Feature` and use the `Tests\Concerns\InteractsWithSolr` trait, which swaps `SolrClientFactory` for `Tests\Support\FakeSolrClientFactory` (queued Guzzle responses + recorded requests) to keep streaming code deterministic.
- When debugging Solr queries, prefer `logger()->debug()` so streaming responses are not corrupted by stray output; chunked downloads will break if you echo inside the response callbacks.
## Patterns To Follow
- Sorting expects Solr syntax like `field asc,other desc`; pass the raw string through from user input only after validating allowed fields, ideally via a whitelist derived from the same `/config/requestHandler` metadata used in Swagger.
- Keep `size` defaults high (currently 10,000,000) only when streaming; for UI or notebook helpers explicitly set lower `size` values to avoid unintended massive exports.
