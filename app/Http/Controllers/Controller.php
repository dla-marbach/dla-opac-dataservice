<?php

namespace App\Http\Controllers;

use Facade\FlareClient\Http\Exceptions\NotFound;
use GuzzleHttp\Client as Client;
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

        // count documents
        $client = new Client(['base_uri' => config('dla_solr.base_uri') . 'admin/']);
        $response = $client->request('GET', 'cores', ['action' => 'STATUS']);
        $responseBody = $response->getBody();
        $jsonResponse = json_decode($responseBody->getContents());

        $docCount = 0;
        $lastModify = 0;
        if ($jsonResponse->status->{$core}) {
            $coreInfo = $jsonResponse->status->{$core};
            $docCount = $coreInfo->index->numDocs;
            $lastModify = $coreInfo->index->lastModified;
        }

        return response(
            json_encode([
           'description' => 'Beschreibung der Schnittstelle',
           'documentCount' => $docCount,
           'collectionCount' => count($config),
           'lastModify' => $lastModify,
           'license' => 'CC-BY 4.0'
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

    public function responseFilter($response, $format, $contentType)
    {
        return response()->stream(function() use($response, $format) {
            $responseBody = $response->getBody();

            $j = 0;
            $dataRemaining = '';
            while(true) {
                $data = $responseBody->read(8192);

                // add remaining data if exists
                if ($dataRemaining) {
                    $data = $dataRemaining . $data;
                    $dataRemaining = '';
                }

                // first check the last new line char in this chunk
                $lastNewLinePosition = strrpos($data, PHP_EOL);

                $dataRemaining = substr($data, $lastNewLinePosition);
                $data = substr($data, 0, $lastNewLinePosition);

                if ($j === 0) {
                    // check if format has no documents
                    if ($format === 'csv') {
                        if ((strlen($data) + strlen($dataRemaining)) < 6000) {
                            // Throw not found exception if solr returns 0 documents
                            throw new NotFoundHttpException();
                        }
                    } else {
                        $documentCounter = 0;
                        // extract solr general info from stream
                        preg_match("/\"numFound\":(\d*)/", $data, $generalData);
                        if (isset($generalData[1])) {
                            $documentCounter = intval($generalData[1]);
                        }
                        if ($documentCounter === 0) {
                            // Throw not found exception if solr returns 0 documents
                            throw new NotFoundHttpException();
                        }
                    }
                }

                if ($format === 'ris') {
                    $data = $this->sanitizeRis($data, $responseBody, $j);
                } else if ($format === 'mods') {
                    $data = $this->sanitizeMods($data, $responseBody, $j);
                } else if ($format === 'dc') {
                    $data = $this->sanitizeDublinCore($data, $responseBody, $j);
                } else if ($format === 'json') {
                    $data = $this->sanitizeJson($data, $responseBody, $j);
                } else if ($format === 'jsonl') {
                    $data = $this->sanitizeJson($data, $responseBody, $j, true);
                }

                if ($responseBody->eof()) {
                    echo $data;
                    break;
                }
                echo $data;
                $j = 1;
            }

            return $response;
        },200, ['content-type' => $contentType, 'Access-Control-Allow-Origin' => '*']);
    }

    private function sanitizeJson(String $data, $responseBody, int $count, $linesOutput = false) {
        if ($count === 0) {
            if ($linesOutput) {
                $data = substr(strstr($data, 'docs":'), 7);
            } else {
                $data = substr(strstr($data, 'docs":'), 6);
            }
        }

        if ($linesOutput) {
            $data = str_replace("\n", '', $data);
            $data = str_replace('{        ', '{', $data);
            $data = str_replace('},', '}' . PHP_EOL, $data);
        }

        if ($responseBody->eof()) {
            if ($linesOutput) {
                $data = substr($data, 0,-5);
            } else {
                $data = substr($data, 0,-4);
            }
        }

        return $data;
    }

    private function sanitizeMods(String $data, $responseBody, int $count) {
        if ($count === 0) {
            // remove solr export field name
            $data = substr(strstr($data, 'exportMODS":"'), 13);

            // remove mods node because of namespace declaration
            $data = preg_replace("/<mods[^>]*>/", '<mods>', $data);

            // add XML header and a root element if more than 2 documents
            $extendData = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
            $extendData = $extendData . '<modsCollection xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.loc.gov/mods/v3" xsi:schemaLocation="http://www.loc.gov/mods/v3 http://www.loc.gov/standards/mods/v3/mods-3-8.xsd">' . PHP_EOL;
            $data = $extendData . $data;
        }

        $replaceArray = ['"', PHP_EOL];
        $searchArray = ['\"', '\n'];
        // json format sanitizing
        $data = preg_replace('/"},[^:]*:"|"},\s*[{},]*\s*{|"}\]\s*}}|"exportMODS":"/', PHP_EOL, $data);
        $data = preg_replace('/^\s*/', '', $data);
        $data = str_replace($searchArray, $replaceArray, $data);
        // remove mods node because of namespace declaration
        $data = preg_replace("/<mods[^>C]*>/", '<mods>', $data);

        $data = preg_replace('/"\s*},{|"\s*}]\s*}|}]\s*}/','', $data);

        $data = preg_replace('/},[{\s},]*{/', '', $data);

        if ($responseBody->eof()) {
            // remove end of json brackets if only one document is given
            $data = preg_replace('/"\}\]\n.*\}\}\n/', '', $data);
            // remove end of json brackets if multiple documents are given
            $data = preg_replace('/"\},\n.*\{\}\]\n.*\}\}\n/', '', $data);
            $data = $data .'</modsCollection>';
        }

        return $data;
    }

    private function sanitizeDublinCore(String $data, $responseBody, $count) {
        if ($count === 0) {
            // remove solr export field name
            $data = substr(strstr($data, 'exportDC":"'), 11);

            // remove mods node because of namespace declaration
            $data = preg_replace("/<oai_dc:dc[^>]*>/", '<oai_dc:dc>', $data);

            // add XML header and a root element if more than 2 documents
            $extendData = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
            $extendData = $extendData . '<records xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd">' . PHP_EOL;
            $data = $extendData . $data;
        }

        $replaceArray = ['"', PHP_EOL];
        $searchArray = ['\"', '\n'];
//        // json format sanitizing
        $data = preg_replace('/"},[^:]*:"|"},\s*[{},]*\s*{|"}\]\s*}}|"exportDC":"/', PHP_EOL, $data);
        $data = preg_replace('/^\s*/', '', $data);
        $data = str_replace($searchArray, $replaceArray, $data);
        // remove mods node because of namespace declaration
        $data = preg_replace("/<oai_dc:dc[^>C]*>/", '<oai_dc:dc>', $data);

        $data = preg_replace('/"\s*},{|"\s*}]\s*}|}]\s*}/','', $data);

        $data = preg_replace('/},[{\s},]*{/', '', $data);

        if ($responseBody->eof()) {
            // remove end of json brackets if only one document is given
            $data = preg_replace('/\}\]\n.*\}\}/', '', $data);
            // remove end of json brackets if multiple documents are given
            $data = preg_replace('/\},\n.*\{\}\]\n.*\}\}/', '', $data);
            $data = $data .'</records>';
        }

        return $data;
    }

    private function sanitizeRis(String $data, $responseBody, $count) {
        if ($count === 0) {
            // remove solr export field name
            $data = substr(strstr($data, 'exportRIS":"'), 12);
        }
        $data = preg_replace('/"},[^:]*:"|"},\s*[{},]*\s*{|"}\]\s*}}|"exportRIS":"/',PHP_EOL, $data);
        $data = str_replace('\n', PHP_EOL, $data);
        $data = str_replace('\"', '"', $data);

        $data = preg_replace('/"\s*},{|"\s*}]\s*}|}]\s*}/','', $data);

        $data = preg_replace('/},[{\s},]*{/', '', $data);

//        if ($responseBody->eof()) {
//            $data = substr($data, 0, -9);
//        }

        return $data;
    }

    public function formattingResponse($solrQueryParams, $format, $client)
    {
        $contentType = 'application/json; charset=utf-8';
        if ($format === 'csv' || $format === '.csv') {
            $format = 'csv';
            $solrQueryParams['query']['wt'] = 'csv';
            $filename = 'export.csv';
        } else if ($format === 'json' || $format === '.json') {
            $format = 'json';
            $solrQueryParams['query']['wt'] = 'json';
            $filename = 'export.json';
            $contentType = 'application/json; charset=utf-8';
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
            $contentType = 'application/json; charset=utf-8';
        } else {
            // default = json
            $solrQueryParams['query']['wt'] = 'json';
            $filename = 'export.json';
            $contentType = 'application/json; charset=utf-8';
        }

        $solrQueryParams['stream'] = true;

        $response = $client->request('GET', 'select', $solrQueryParams);

        return $this->responseFilter($response, $format, $contentType);
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

        $format = 'json';
        $solrQueryParams['query']['rows'] = 0;

        if ($solrQueryParams['query']['q']) {
            $solrQueryParams['query']['q'] = config('dla_solr.staticFilter') . ' AND ' . $solrQueryParams['query']['q'];
        } else {
            $solrQueryParams['query']['q'] = config('dla_solr.staticFilter');
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
