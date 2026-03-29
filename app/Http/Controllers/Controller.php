<?php

namespace App\Http\Controllers;

use Facade\FlareClient\Http\Exceptions\NotFound;
use GuzzleHttp\Client as Client;
use JsonMachine\Items;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;


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
            $solrParamArray['query']['q'] = $request->input('q');
        }

        if ($request->input('fields')) {
            // comma separated
            if ($request->input('fields') !== '*') {
                $solrParamArray['query']['fl'] = $request->input('fields');
            }
        }

        return $solrParamArray;
    }

    public function getInfo()
    {
        $config = config('dla_collection');
        $core = config('dla_solr.core');

        // count documents using staticFilter
        $countClient = new Client(['base_uri' => config('dla_solr.base_uri') . $core . '/select']);
        $countParams['query']['q'] = config('dla_solr.staticFilter');
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
        return response()->stream(function() use($response, $format) {
            $body = $response->getBody();

            // CSV and TSV are native Solr formats — stream directly without JSON parsing
            if ($format === 'csv' || $format === 'tsv') {
                $firstChunk = true;
                while (!$body->eof()) {
                    $chunk = $body->read(8192);
                    if ($firstChunk) {
                        if (substr_count($chunk, PHP_EOL) <= 1) {
                            throw new NotFoundHttpException();
                        }
                        $firstChunk = false;
                    }
                    echo $chunk;
                }
                return;
            }

            // All other formats: use json-machine for robust streaming JSON parsing
            $chunks = (function() use ($body) {
                while (!$body->eof()) {
                    yield $body->read(8192);
                }
            })();

            $items = Items::fromIterable($chunks, [
                'pointer' => ['/response/numFound', '/response/docs']
            ]);

            $i = 0;
            foreach ($items as $item) {
                // Check document count before any output
                if ($items->getMatchedJsonPointer() === '/response/numFound') {
                    if ($item === 0) {
                        throw new NotFoundHttpException();
                    }
                    continue;
                }

                // Output format header before first document
                if ($i === 0) {
                    if ($format === 'mods') {
                        echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
                        echo '<modsCollection xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.loc.gov/mods/v3" xsi:schemaLocation="http://www.loc.gov/mods/v3 http://www.loc.gov/standards/mods/v3/mods-3-8.xsd">' . PHP_EOL;
                    } elseif ($format === 'dc') {
                        echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
                        echo '<records xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd">' . PHP_EOL;
                    } elseif ($format === 'json') {
                        echo '[';
                    }
                } elseif ($format === 'json') {
                    echo ',';
                }

                if ($format === 'mods') {
                    echo preg_replace('/<mods[^>]*>/', '<mods>', $item->exportMODS ?? '') . PHP_EOL;
                } elseif ($format === 'dc') {
                    echo preg_replace('/<oai_dc:dc[^>]*>/', '<oai_dc:dc>', $item->exportDC ?? '') . PHP_EOL;
                } elseif ($format === 'ris') {
                    echo ($item->exportRIS ?? '') . PHP_EOL;
                } elseif ($format === 'json') {
                    echo json_encode($item, JSON_UNESCAPED_UNICODE);
                } elseif ($format === 'jsonl') {
                    echo json_encode($item, JSON_UNESCAPED_UNICODE) . PHP_EOL;
                }

                $i++;
            }

            if ($i === 0) {
                throw new NotFoundHttpException();
            }

            if ($format === 'mods') {
                echo '</modsCollection>';
            } elseif ($format === 'dc') {
                echo '</records>';
            } elseif ($format === 'json') {
                echo ']';
            }

        }, 200, [
            'content-type' => $contentType,
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    public function formattingResponse($solrQueryParams, $format, $client)
    {
        $contentType = 'application/json; charset=utf-8';
        if ($format === 'csv' || $format === '.csv') {
            $format = 'csv';
            $solrQueryParams['query']['wt'] = 'csv';
            $filename = 'export.csv';
        } else if ($format === 'tsv' || $format === '.tsv') {
            $format = 'tsv';
            $solrQueryParams['query']['wt'] = 'csv';
            $solrQueryParams['query']['csv.separator'] = "\t";
            $filename = 'export.tsv';
            $contentType = 'text/tab-separated-values; charset=utf-8';
        } else if ($format === 'json' || $format === '.json') {
            $format = 'json';
            $solrQueryParams['query']['wt'] = 'json';
            $filename = 'export.json';
            $contentType = 'application/json; charset=utf-8';
        } else if ($format === 'ris' || $format === '.ris') {
            $format = 'ris';
            $solrQueryParams['query']['fl'] = 'exportRIS';
            $filename = 'export.ris';
            $contentType = 'text';
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
            $contentType = 'application/json; charset=utf-8';
        } else {
            // default = json
            $solrQueryParams['query']['wt'] = 'json';
            $filename = 'export.json';
            $contentType = 'application/json; charset=utf-8';
        }

        $solrQueryParams['stream'] = true;

        $response = $client->request('GET', 'select', $solrQueryParams);

        return $this->responseFilter($response, $format, $contentType, $filename);
    }

    public function getRecord(Request $request, $id, $format = 'json')
    {
        $solrQueryParams = [];
        $solrQueryParams = $this->transformGivenParameter($request);

        $client = new Client(['base_uri' => config('dla_solr.base_uri') . config('dla_solr.core') . '/select']);
        $solrQueryParams['query']['q'] = config('dla_solr.staticFilter') . ' AND id:(' . $id . ')';

        ob_end_clean();

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

        if ($solrQueryParams['query']['q']) {
            $solrQueryParams['query']['q'] = config('dla_solr.staticFilter') . ' AND ' . $solrQueryParams['query']['q'];
        } else {
            $solrQueryParams['query']['q'] = config('dla_solr.staticFilter');
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
        $response = $client->request('GET', 'select', $solrQueryParams);
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

        if ($solrQueryParams['query']['q']) {
            $solrQueryParams['query']['q'] = config('dla_solr.staticFilter') . ' AND ' . $solrQueryParams['query']['q'];
        } else {
            $solrQueryParams['query']['q'] = config('dla_solr.staticFilter');
        }

        ob_end_clean();

        return $this->formattingResponse($solrQueryParams, $format, $client);
    }

    public function getRecordsById(Request $request, $format = 'json')
    {
        $client = new Client(['base_uri' => config('dla_solr.base_uri') . config('dla_solr.core') . '/']);
        $solrQueryParams = [];
        $solrQueryParams['query']['q'] = config('dla_solr.staticFilter');

        $ids = $request->input('ids');

        $idsExp = explode(',', $ids);
        $convertIdToQuery = ' AND id:(';
        $i = 0;
        foreach ($idsExp as $id) {
            if ($i === 0) {
                $convertIdToQuery .= $id;
            } else {
                $convertIdToQuery .= ' OR ' . $id;
            }
            $i++;
        }
        $convertIdToQuery .= ')';
        $solrQueryParams['query']['q'] .= $convertIdToQuery;

        ob_end_clean();

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
