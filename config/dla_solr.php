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

    'base_uri' => env('DLA_SOLR_BASE_URI', ''),
    'core' => env('DLA_SOLR_BASE_CORE', ''),
    'staticFilter' => '*'

];