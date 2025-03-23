<?php
/**
 * @category    dla_solr
 * @package     Dla_solr
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Solr config
    |--------------------------------------------------------------------------
    |
    | DLA solr configuration
    |
    */

    'base_uri' => env('DLA_SOLR_BASE_URI', 'http://datastream.dla-marbach.de:8983/solr/'),
    'core' => env('DLA_SOLR_BASE_CORE', 'internformat'),
    'staticFilter' => '*'

];