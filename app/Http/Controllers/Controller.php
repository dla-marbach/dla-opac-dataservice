<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client as Client;
use JsonMachine\Items;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;


    private static ?string $cachedDefaultFieldList = null;

    private function getRepeatedQueryParameter(Request $request, string $parameterName): array
    {
        $values = [];
        $queryString = $request->server('QUERY_STRING', '');

        if (is_string($queryString) && $queryString !== '') {
            foreach (explode('&', $queryString) as $queryPart) {
                if ($queryPart === '') {
                    continue;
                }

                [$rawKey, $rawValue] = array_pad(explode('=', $queryPart, 2), 2, '');
                $decodedKey = urldecode($rawKey);

                if ($decodedKey !== $parameterName && $decodedKey !== $parameterName . '[]') {
                    continue;
                }

                $decodedValue = urldecode($rawValue);
                if ($decodedValue !== '') {
                    $values[] = $decodedValue;
                }
            }
        }

        if (!empty($values)) {
            return $values;
        }

        $fallbackValue = $request->query($parameterName);
        if (is_string($fallbackValue) && $fallbackValue !== '') {
            return [$fallbackValue];
        }

        if (is_array($fallbackValue)) {
            foreach ($fallbackValue as $value) {
                if (is_string($value) && $value !== '') {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }

    private function buildSolrQueryString(array $queryParams): string
    {
        $parts = [];

        foreach ($queryParams as $name => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $arrayValue) {
                    if ($arrayValue === null) {
                        continue;
                    }

                    $parts[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $arrayValue);
                }
                continue;
            }

            $parts[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $value);
        }

        return implode('&', $parts);
    }

    private function requestSolrSelect(Client $client, array $solrQueryParams)
    {
        if (isset($solrQueryParams['query']) && is_array($solrQueryParams['query'])) {
            $solrQueryParams['query'] = $this->buildSolrQueryString($solrQueryParams['query']);
        }

        return $client->request('GET', 'select', $solrQueryParams);
    }

    private function getDefaultFieldList(): string
    {
        if (self::$cachedDefaultFieldList === null) {
            $client = new Client(['base_uri' => config('dla_solr.base_uri') . config('dla_solr.core') . '/config/']);
            $response = $client->request('GET', 'requestHandler', ['componentName' => '/select']);
            $jsonResponse = json_decode($response->getBody()->getContents());
            $select = '/select';
            self::$cachedDefaultFieldList = $jsonResponse->config->requestHandler->{$select}->defaults->fl;
        }
        return self::$cachedDefaultFieldList;
    }

    public function transformGivenParameter(Request $request)
    {
        $solrParamArray = [];
        if ($request->input('sort')) {
            // comma separated
            $solrParamArray['query']['sort'] = $request->input('sort');
        }

        if ($request->input('size')) {
            $solrParamArray['query']['rows'] = intval($request->input('size'));
        } else {
            $solrParamArray['query']['rows'] = 10000000;
        }

        if ($request->input('from')) {
            $solrParamArray['query']['start'] = intval($request->input('from') - 1);
            if ($solrParamArray['query']['start'] < 0) {
                $solrParamArray['query']['start'] = 0;
            }
        }

        if ($request->input('q')) {
            $q = $request->input('q');
            $q = preg_replace('/(\w+)\s*:\s*\("RANGE\s+(\d+)\s+TO\s+(\d+)"\)/', '$1:[$2 TO $3]', $q);
            $solrParamArray['query']['q'] = $q;
        }

        if ($request->input('fields')) {
            // comma separated
            if ($request->input('fields') !== '*') {
                $solrParamArray['query']['fl'] = $request->input('fields');
            }
        }

        $fqValues = $this->getRepeatedQueryParameter($request, 'fq');
        if (!empty($fqValues)) {
            $solrParamArray['query']['fq'] = $fqValues;
        }

        return $solrParamArray;
    }

    public function getInfo()
    {
        $config = config('dla_collection');
        $core = config('dla_solr.core');

        // count documents
        $countClient = new Client(['base_uri' => config('dla_solr.base_uri') . $core . '/select']);
        $countParams['query']['q'] = '*:*';
        $countParams['query']['rows'] = 0;
        $countResponse = $countClient->request('GET', 'select', $countParams);
        $countJson = json_decode($countResponse->getBody()->getContents());
        $docCount = $countJson->response->numFound ?? 0;

        // get lastModified from admin endpoint
        $adminClient = new Client(['base_uri' => config('dla_solr.base_uri') . 'admin/']);
        $adminResponse = $adminClient->request('GET', 'cores', ['action' => 'STATUS']);
        $adminJson = json_decode($adminResponse->getBody()->getContents());

        $lastModify = 0;
        if ($adminJson->status->{$core}) {
            $lastModify = $adminJson->status->{$core}->index->lastModified;
        }

        return response(
            json_encode([
           'description' => 'https://github.com/dla-marbach/dla-opac-dataservice/blob/main/README.md',
           'documentCount' => $docCount,
           'collectionCount' => count($config),
           'lastModify' => $lastModify,
           'license' => 'CC0 (Public Domain)'
        ], JSON_UNESCAPED_UNICODE)
        )
            ->header('content-type', 'application/json; charset=utf-8')
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function getInfoSchema()
    {
        // count documents
        $client = new Client(['base_uri' => config('dla_solr.base_uri') . config('dla_solr.core') . '/' . 'schema/']);
        $solrQueryParams['query']['wt'] = 'json';

        $response = $client->request('GET', 'fields', $solrQueryParams);
        $responseBody = $response->getBody();
        $jsonResponse = json_decode($responseBody->getContents());

        $output = [];
        foreach ($jsonResponse->fields as $fieldInfo) {
            // do not return internal and export field names
            if (substr($fieldInfo->name, 0, 1) !== '_'
                && substr($fieldInfo->name, 0, 6) !== 'export') {
                $output[] = ['name' => $fieldInfo->name, 'type' => $fieldInfo->type];
            }
        }

        return response(
            json_encode($output, JSON_UNESCAPED_UNICODE)
        )->header('content-type', 'application/json; charset=utf-8')
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function responseFilter($response, $format, $contentType, $filename = '')
    {
        $acceptsGzip = function_exists('deflate_init')
            && str_contains(request()->header('Accept-Encoding', ''), 'gzip');

        $headers = [
            'content-type' => $contentType,
            'Access-Control-Allow-Origin' => '*',
        ];

        if ($acceptsGzip) {
            $headers['Content-Encoding'] = 'gzip';
        }

        return response()->stream(function() use($response, $format, $acceptsGzip) {
            $ctx = null;
            if ($acceptsGzip) {
                $result = deflate_init(ZLIB_ENCODING_GZIP);
                $ctx = ($result !== false) ? $result : null;
            }

            $write = function(string $data) use ($ctx, $acceptsGzip): void {
                if ($acceptsGzip && $ctx !== null) {
                    $compressed = deflate_add($ctx, $data, ZLIB_SYNC_FLUSH);
                    if ($compressed !== false) {
                        echo $compressed;
                    }
                } else {
                    echo $data;
                }
            };

            $body = $response->getBody();

            // CSV and TSV are native Solr formats — stream directly without JSON parsing
            if ($format === 'csv' || $format === 'tsv') {
                while (!$body->eof()) {
                    $write($body->read(8192));
                }
                if ($acceptsGzip && $ctx !== null) {
                    $final = deflate_add($ctx, '', ZLIB_FINISH);
                    if ($final !== false) {
                        echo $final;
                    }
                }
                return;
            }

            // All other formats: use json-machine for robust streaming JSON parsing
            $chunks = (function() use ($body) {
                while (!$body->eof()) {
                    yield $body->read(8192);
                }
            })();

            $items = Items::fromIterable($chunks, ['pointer' => '/response/docs']);

            $i = 0;
            foreach ($items as $item) {
                // Output format header before first document
                if ($i === 0) {
                    if ($format === 'mods') {
                        $write('<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);
                        $write('<modsCollection xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.loc.gov/mods/v3" xsi:schemaLocation="http://www.loc.gov/mods/v3 http://www.loc.gov/standards/mods/v3/mods-3-8.xsd">' . PHP_EOL);
                    } elseif ($format === 'dc') {
                        $write('<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);
                        $write('<records xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd">' . PHP_EOL);
                    } elseif ($format === 'json') {
                        $write('[');
                    }
                } elseif ($format === 'json') {
                    $write(',');
                }

                if ($format === 'mods') {
                    $write(preg_replace('/<mods[^>]*>/', '<mods>', $item->exportMODS ?? '') . PHP_EOL);
                } elseif ($format === 'dc') {
                    $write(preg_replace('/<oai_dc:dc[^>]*>/', '<oai_dc:dc>', $item->exportDC ?? '') . PHP_EOL);
                } elseif ($format === 'ris') {
                    $write(($item->exportRIS ?? '') . PHP_EOL);
                } elseif ($format === 'json') {
                    $write(json_encode($item, JSON_UNESCAPED_UNICODE));
                } elseif ($format === 'jsonl') {
                    $write(json_encode($item, JSON_UNESCAPED_UNICODE) . PHP_EOL);
                }

                $i++;
            }

            if ($format === 'mods') {
                $write('</modsCollection>');
            } elseif ($format === 'dc') {
                $write('</records>');
            } elseif ($format === 'json') {
                $write(']');
            }

            if ($acceptsGzip && $ctx !== null) {
                $final = deflate_add($ctx, '', ZLIB_FINISH);
                if ($final !== false) {
                    echo $final;
                }
            }

        }, 200, $headers);
    }

    public function formattingResponse($solrQueryParams, $format, $client)
    {
        $contentType = 'application/json; charset=utf-8';
        if ($format === 'csv' || $format === '.csv') {
            $format = 'csv';
            $solrQueryParams['query']['wt'] = 'csv';
            $filename = 'export.csv';
            if (!isset($solrQueryParams['query']['fl'])) {
                $solrQueryParams['query']['fl'] = $this->getDefaultFieldList();
            }
        } else if ($format === 'tsv-light' || $format === '.tsv-light') {
            $format = 'tsv';
            $solrQueryParams['query']['wt'] = 'csv';
            $solrQueryParams['query']['fl'] = 'display,displayName,displayAddition1,displayAddition2,id,filterAuthority_mv,filterBibliography_mv,filterCollection_mv,filterDateRange_mv,filterDigital,filterFormContent_mv,filterLanguage_mv,filterLocation_mv,filterMedium_mv,filterSource,filterSubject_mv,filterType_mv,url';
            $solrQueryParams['query']['csv.separator'] = "\t";
            $solrQueryParams['query']['csv.mv.separator'] = "\n";
            $filename = 'export.tsv';
            $contentType = 'text/plain; charset=utf-8';
        } else if ($format === 'tsv' || $format === '.tsv') {
            $format = 'tsv';
            $solrQueryParams['query']['wt'] = 'csv';
            $solrQueryParams['query']['csv.separator'] = "\t";
            $solrQueryParams['query']['csv.mv.separator'] = "\n";
            $filename = 'export.tsv';
            $contentType = 'text/plain; charset=utf-8';
            if (!isset($solrQueryParams['query']['fl'])) {
                $solrQueryParams['query']['fl'] = $this->getDefaultFieldList();
            }
        } else if ($format === 'json' || $format === '.json') {
            $format = 'json';
            $solrQueryParams['query']['wt'] = 'json';
            $filename = 'export.json';
            $contentType = 'application/json; charset=utf-8';
            if (!isset($solrQueryParams['query']['fl'])) {
                $solrQueryParams['query']['fl'] = $this->getDefaultFieldList();
            }
        } else if ($format === 'ris' || $format === '.ris') {
            $format = 'ris';
            $solrQueryParams['query']['fl'] = 'exportRIS';
            $filename = 'export.ris';
            $contentType = 'text/plain; charset=utf-8';
        } else if ($format === 'mods' || $format === '.mods') {
            $format = 'mods';
            $solrQueryParams['query']['fl'] = 'exportMODS';
            $filename = 'export.xml';
            $contentType = 'text/xml; charset=utf-8';
        } else if ($format === 'dc' || $format === '.dc') {
            $format = 'dc';
            $solrQueryParams['query']['fl'] = 'exportDC';
            $filename = 'export.xml';
            $contentType = 'text/xml; charset=utf-8';
        } else if ($format === 'jsonl' || $format === '.jsonl') {
            $format = 'jsonl';
            $solrQueryParams['query']['wt'] = 'json';
            $filename = 'export.json';
            $contentType = 'application/jsonl; charset=utf-8';
            if (!isset($solrQueryParams['query']['fl'])) {
                $solrQueryParams['query']['fl'] = $this->getDefaultFieldList();
            }
        } else {
            // default = json
            $solrQueryParams['query']['wt'] = 'json';
            $filename = 'export.json';
            $contentType = 'application/json; charset=utf-8';
        }

        $countParams = ['query' => array_filter([
            'q'    => $solrQueryParams['query']['q'],
            'rows' => 1,
            'wt'   => 'json',
            'fl'   => $solrQueryParams['query']['fl'] ?? null,
        ], fn($v) => $v !== null)];
        if (isset($solrQueryParams['query']['fq'])) {
            $countParams['query']['fq'] = $solrQueryParams['query']['fq'];
        }
        $countJson = json_decode($this->requestSolrSelect($client, $countParams)->getBody()->getContents());
        $firstDoc = $countJson->response->docs[0] ?? null;
        if (($countJson->response->numFound ?? 0) === 0 || empty($countJson->response->docs) || empty((array) $firstDoc)) {
            return response('', 204)->header('Access-Control-Allow-Origin', '*');
        }

        $solrQueryParams['stream'] = true;

        $response = $this->requestSolrSelect($client, $solrQueryParams);

        return $this->responseFilter($response, $format, $contentType, $filename);
    }

    public function getRecord(Request $request, $id, $format = 'json')
    {
        $solrQueryParams = [];
        $solrQueryParams = $this->transformGivenParameter($request);

        $client = new Client(['base_uri' => config('dla_solr.base_uri') . config('dla_solr.core') . '/select']);
        $solrQueryParams['query']['q'] = 'id:' . $id;

        return $this->formattingResponse($solrQueryParams, $format, $client);

    }

    public function getRecordsCount(Request $request)
    {
        $solrQueryParams = [];
        $solrQueryParams = $this->transformGivenParameter($request);
        $client = new Client(['base_uri' => config('dla_solr.base_uri') . config('dla_solr.core') . '/select']);

        if ($request->input('format')) {
            $format = $request->input('format');
        } else {
            $format = 'json';
        }

        $solrQueryParams['query']['rows'] = 0;

        if (empty($solrQueryParams['query']['q'])) {
            $solrQueryParams['query']['q'] = '*:*';
        }

        if ($format === 'ris') {
            $solrQueryParams['query']['q'] = $solrQueryParams['query']['q'] . ' AND exportRIS:*';
        }
        if ($format === 'mods') {
            $solrQueryParams['query']['q'] = $solrQueryParams['query']['q'] . ' AND exportMODS:*';
        }
        if ($format === 'dc') {
            $solrQueryParams['query']['q'] = $solrQueryParams['query']['q'] . ' AND exportDC:*';
        }

        // count documents
        $response = $this->requestSolrSelect($client, $solrQueryParams);
        $responseBody = $response->getBody();
        $jsonResponse = json_decode($responseBody->getContents());

        return response(
            json_encode(['documentCount' => $jsonResponse->response->numFound], JSON_UNESCAPED_UNICODE)
        )->header('content-type', 'application/json; charset=utf-8')
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function getRecords(Request $request)
    {
        $solrQueryParams = [];
        $solrQueryParams = $this->transformGivenParameter($request);

        $client = new Client(['base_uri' => config('dla_solr.base_uri') . config('dla_solr.core') . '/select']);

        if ($request->input('format')) {
            $format = $request->input('format');
        } else {
            $format = 'json';
        }

        if (empty($solrQueryParams['query']['q'])) {
            $solrQueryParams['query']['q'] = '*:*';
        }

        return $this->formattingResponse($solrQueryParams, $format, $client);
    }

    public function getCollection(Request $request, $id, $format = 'json')
    {
        $config = config('dla_collection');

        if (array_key_exists($id, $config)) {
            return redirect()->route('records',
                [
                    'q' => $config[$id]['query'],
                    'format' => $format,
                    'from' => $request->has('from') ? $request->input('from') : null,
                    'size' => $request->has('size') ? $request->input('size') : null,
                    'fields' => $request->has('fields') ? $request->input('fields') : null,
                    'sort' => $request->has('sort') ? $request->input('sort') : null
                ]);
        }
    }

    public function getCollections()
    {
        $config = config('dla_collection');

        foreach ($config as $collectionName => $collection) {
            $output[] = ['id' => $collectionName, 'name' => $collection['info'], 'url' => $collection['url']];
        }

        return response(
            json_encode($output, JSON_UNESCAPED_UNICODE)
        )->header('content-type', 'application/json; charset=utf-8')
            ->header('Access-Control-Allow-Origin', '*');
    }

}
