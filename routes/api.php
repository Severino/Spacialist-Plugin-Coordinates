<?php

use Illuminate\Support\Facades\Route;

// Mounted under api/v1/coordinates (slug of the plugin name)
Route::middleware('auth:sanctum')->group(function() {
    Route::get('/sources', 'SourceController@index');
    Route::post('/sources', 'SourceController@store');
    Route::put('/sources/{id}', 'SourceController@update')->where('id', '[0-9]+');
    Route::post('/sources/{id}/recompute', 'SourceController@recompute')->where('id', '[0-9]+');
    Route::delete('/sources/{id}', 'SourceController@destroy')->where('id', '[0-9]+');
    Route::get('/entity/{id}/values', 'SourceController@values')->where('id', '[0-9]+');
});
