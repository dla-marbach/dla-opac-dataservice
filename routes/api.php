<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['prefix' => 'v1'], function () {
    Route::get('info', ['as' => 'info', 'uses' => 'App\Http\Controllers\Controller@getInfo']);
    Route::get('info/schema', ['as' => 'schema', 'uses' => 'App\Http\Controllers\Controller@getInfoSchema']);

    Route::get('collections', ['as' => 'collections', 'uses' => 'App\Http\Controllers\Controller@getCollections']);
    Route::get('collection/{id}{format?}', ['as' => 'collection', 'uses' => 'App\Http\Controllers\Controller@getCollection'])
        ->where('id', '[0-9]{1,10}');

    Route::get('record/{id}{format?}', ['as' => 'record', 'uses' => 'App\Http\Controllers\Controller@getRecord'])
        ->where('id', '[A-Z]{2,4}[0-9]{6,10}');

    Route::get('records', ['as' => 'records', 'uses' => 'App\Http\Controllers\Controller@getRecords']);
    Route::get('records/count', ['as' => 'recordsCount', 'uses' => 'App\Http\Controllers\Controller@getRecordsCount']);
});
