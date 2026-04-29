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


    private const DEFAULT_FIELD_LIST = 'accession_display,accession_id,accessionNumber,accessLevel,acronym_mv,broadcastDate,broadcastStation,callNumberBibliographic_mv,callNumberCollection_mv,callNumberItem,callNumberItemSuffix,callNumberReadingRoom,category,categoryContent_mv,categoryEntity_mv,categoryIndex_mv,categoryMedia_mv,categoryMedium_mv,categoryPublication_mv,categorySub,categorySubSub,child_display_mv,child_id_mv,classificationAuthor_display_mv,classificationAuthor_id_mv,classificationChain,classification_display_mv,classificationForm_display_mv,classificationForm_id_mv,classification_id_mv,classificationNewspaper_comment_mv,classificationNewspaper_text_mv,classificationOther_comment_mv,classificationOther_text_mv,classificationSubject_display_mv,classificationSubject_id_mv,classificationSubjectOther_mv,content,corporationAbout_comment_mv,corporationAbout_display_mv,corporationAbout_id_mv,corporationAbout_role_mv,corporationAbout_type_mv,corporationAt_display_mv,corporationAt_id_mv,corporationBy_comment_mv,corporationByConference_creator_mv,corporationByConference_display_mv,corporationByConference_id_mv,corporationByConference_role_mv,corporationBy_creator_mv,corporationBy_display_mv,corporationBy_id_mv,corporationBy_role_mv,corporationByTerritory_creator_mv,corporationByTerritory_display_mv,corporationByTerritory_id_mv,corporationByTerritory_role_mv,corporationBy_type_mv,corporation_display_mv,corporation_id_mv,corporationTo_comment_mv,corporationTo_display_mv,corporationTo_id_mv,corporationTo_type_mv,country_mv,dateActivityEnd,dateActivityStart,dateCataloged,dateLifespanComment_mv,dateLifespanEnd,dateLifespanStart,dateModified,dateNote_mv,dateOrigin,dateOriginComment_mv,dateOriginEnd,dateOriginStart,dateOtherComment_mv,dateOther_mv,dateRetention,department,description_text_mv,description_type_mv,digitalObject_display_mv,digitalObject_id_mv,dimension_comment_mv,dimension_depth_mv,dimension_diameter_mv,dimension_height_mv,dimension_width_mv,display,edition,editionNormalized,enveloped,extent,extentFormat,extentIllustrations_mv,extentSupplements,filePath,filterCollection_mv,gender,genre,genreOther_mv,genreSub,genreSubOther_mv,gnd_id_mv,gndRelation_comment_mv,gndRelation_id_mv,gndRelation_text_mv,gndRelation_type_mv,gnd_type_mv,headword_mv,holding_display_mv,holding_id_mv,host_display_mv,host_id_mv,id,identifier_id_mv,identifier_type_mv,index,inscription,inventory,inventoryMissing,inventoryNumber,isbn_mv,ismn_mv,issn_mv,item_display_mv,item_holding_display_mv,item_holding_id_mv,item_id_mv,itemization_extent_mv,itemization_status_mv,itemization_unit_mv,itemNumber,journalIssue_display_mv,journalIssue_id_mv,language_mv,languageOriginal_mv,library_display_mv,library_id_mv,locationBoxNumber,locationComment,locationFolderNumber,location_mv,manifestation_display,manifestation_id,manuscript_display_mv,manuscript_id_mv,material,mediaNumber,microform_mv,nameAlternative_comment_mv,nameAlternative_name_mv,nameAlternative_suffix_mv,nameAlternative_type_mv,nameFormerOrLater_display_mv,nameFormerOrLater_id_mv,nameOriginal_mv,nameTemporary_display_mv,nameTemporary_id_mv,notation,note,noteBibliography_mv,noteClassification,noteContent_mv,noteDimension,noteExplanatory_text_mv,noteExplanatory_type_mv,noteFootnote_text_mv,noteFootnote_type_mv,noteObjectType,noteOther_mv,noteProvenance_text_mv,noteProvenance_type_mv,noteRemark_text_mv,noteRemark_type_mv,noteRequirements_mv,object_display,object_id,occupation_mv,order_extent_mv,order_status_mv,order_unit_mv,parent_display_mv,parent_id_mv,parentIssueDate,parentIssueNumber,parentIssuePage,parentIssueVolume,parentIssueYear,parentIssueYearVolume,parentTitleOriginal_mv,parent_type_mv,parentVolume_mv,parentVolumeNormalized_mv,parentVolumeTotal,personAbout_comment_mv,personAbout_display_mv,personAbout_id_mv,personAbout_role_mv,personAt_display_mv,personAt_id_mv,personBy_comment_mv,personBy_creator_mv,personBy_display_mv,personBy_id_mv,personBy_role_mv,person_display_mv,person_id_mv,personTo_comment_mv,personTo_display_mv,personTo_id_mv,personTo_role_mv,photoNegativeNumber_mv,placeBirth,placeDeath,place_mv,predecessor_display_mv,predecessor_id_mv,provenance,publicationHistory,publisher_display_mv,publisher_id_mv,publisherOriginalLocation_mv,publisherOriginal_text_mv,publisherOriginal_type_mv,reference_text_mv,reference_type_mv,relation_display_mv,relation_id_mv,relation_type_mv,scholarlyPublication_date_mv,scholarlyPublication_location_mv,scholarlyPublication_text_mv,scholarlyPublication_type_mv,seeAlso_display_mv,seeAlso_id_mv,sequence_mv,sortingKey,sortingKeySub,sortingTitleMain,sortingTitleOther,statusCataloging,statusEditing,statusItem,statusJournalReview,statusLoan,statusPreservation,storageArea,subject_display_mv,subject_id_mv,subjectLocation_comment_mv,subjectLocation_display_mv,subjectLocation_id_mv,subjectLocation_type_mv,subjectOther_mv,subject_type_mv,subseries,successor_display_mv,successor_id_mv,title,titleMain_category,titleMain_comment,titleMain_text,titleMain_type,titleOriginal,titleOther_category_mv,titleOther_comment_mv,titleOther_language_mv,titleOther_text_mv,titleOther_type_mv,titlePrefix,titleResponsability,titleResponsabilityFull,titleShort,titleSuffix,titleType,url,usageRestriction,usageRestrictionComment,usedFor_display_mv,usedFor_id_mv,useTerm_display_mv,useTerm_id_mv,vendor_id_mv,vendor_type_mv,virtualRecording,website_description_mv,website_url_mv,workAbout_display_mv,workAbout_id_mv,workCompilation_display_mv,workCompilation_id_mv,work_display_mv,work_id_mv';

    private function getDefaultFieldList(): string
    {
        return self::DEFAULT_FIELD_LIST;
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
            if (!isset($solrQueryParams['query']['fl'])) {
                $solrQueryParams['query']['fl'] = $this->getDefaultFieldList();
            }
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
            if (!isset($solrQueryParams['query']['fl'])) {
                $solrQueryParams['query']['fl'] = $this->getDefaultFieldList();
            }
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
            $solrQueryParams['query']['q'] = config('dla_solr.staticFilter') . ' AND (' . $solrQueryParams['query']['q'] . ')';
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
            $solrQueryParams['query']['q'] = config('dla_solr.staticFilter') . ' AND (' . $solrQueryParams['query']['q'] . ')';
        } else {
            $solrQueryParams['query']['q'] = config('dla_solr.staticFilter');
        }

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
