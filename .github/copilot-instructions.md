# DLA Data+ Copilot Guide
## Architecture
- Laravel 8 app in `app/Http/Controllers` exposes every public endpoint in `routes/web.php` (all prefixed with `/v1`); extend the API by adding controller methods there rather than scattering Solr calls elsewhere.
- Solr access is centralized through Guzzle clients configured in `config/dla_solr.php`; always reuse those settings and merge queries with `config('dla_solr.staticFilter')` to avoid leaking non-public documents.
- `transformGivenParameter()` converts user params into Solr syntax (sort, rows, start, fields); when adding filters, extend this helper so pagination and validation stay uniform.
- Streaming happens in `responseFilter()` plus `formattingResponse()`, which read Solr chunks directly and throw `NotFoundHttpException` on empty results; new export types must plug into this pipeline to preserve memory-friendly downloads.
- Format sanitizers (`sanitizeJson|sanitizeMods|sanitizeDublinCore|sanitizeRis`) strip Solr metadata, normalize namespaces, and add headers; mirror that approach for any future `exportXYZ` fields returned by Solr.
- Collections are data-driven via `config/dla_collection.php`; `/v1/collection/{id}` redirects to `/v1/records` with the stored query, so keep IDs numeric and queries Solr-safe.
- The legacy HTML form in `resources/views/frontend.blade.php` posts to `/v1/query` and `/v1/id`; update those routes if you rename controller methods so manual testers keep a working form.
## Documentation & Swagger
- Swagger UI routes come from `nextapps/laravel-swagger-ui`; `SwaggerUiServiceProvider` binds its controller to `OverrideOpenApiJsonController`, which hits Solr's `/config/requestHandler` endpoint to populate the `fields` enum dynamically before returning the spec.
- The spec path is configured via `config/swagger-ui.php` (defaults to `resources/swagger/openapi.json`); the repo keeps a public copy at `public/openapi.json` for static hosting, so update both when changing endpoints.
- Customized Swagger assets live under `resources/views/vendor/swagger-ui`, `resources/css/swagger-ui.css`, and `resources/js/swagger-ui-bundle.js`; `webpack.mix.js` copies them to `public/`, so run `npm run dev` after editing.
## Environment & Configuration
- Required env vars: `DLA_SOLR_BASE_URI` (with trailing slash), `DLA_SOLR_BASE_CORE`, and `APP_URL` for Swagger server metadata; adjust `DLA_SOLR_STATICFILTER` if production cores include drafts.
- Guzzle calls read `config('dla_solr.staticFilter')` before appending user queries; if you introduce new Solr endpoints ensure that filter is still enforced.
- `config('dla_collection')` entries expose public URLs inside API responses; keep `info`, `query`, and `url` in sync when curating new sub-collections.
- Example notebooks in `examples/` hit the deployed API; treat them as integration smoke tests when changing query syntax or response formats.
## Developer Workflows
- Install PHP deps with `composer install`, copy `.env`, set Solr variables, then run `php artisan key:generate`.
- Serve locally via `php artisan serve`; endpoints live under `http://127.0.0.1:8000/v1` and proxy whatever Solr instance you configured.
- Build assets with `npm install && npm run dev`; production deploys use `npm run prod`, so any new CSS/JS must be compatible with Laravel Mix v6.
- Feature tests belong in `tests/Feature`; mock Guzzle responses (rather than hitting live Solr) to keep streaming code deterministic, and remember that `ob_end_clean()` in controllers will interfere with `dump()` debugging.
- When debugging Solr queries, prefer `logger()->debug()` so streaming responses are not corrupted by stray output; chunked downloads will break if you echo inside the response callbacks.
## Patterns To Follow
- Always validate `ids` inputs (comma-separated list) before calling `getRecordsById()`; feed sanitized IDs into the OR query builder to avoid Solr injection.
- Sorting expects Solr syntax like `field asc,other desc`; pass the raw string through from user input only after validating allowed fields, ideally via a whitelist derived from the same `/config/requestHandler` metadata used in Swagger.
- Keep `size` defaults high (currently 10,000,000) only when streaming; for UI or notebook helpers explicitly set lower `size` values to avoid unintended massive exports.
