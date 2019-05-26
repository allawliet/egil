<?php

use Illuminate\Support\Facades\Route;

/**
 * Routes for all hotel agreements in the system
 *
 * @return void
 */
function agreementRoutes()
{
    //-- Someone else without an account can fill out the hotel agreement
    // make sure to send that person a signed URL
    Route::get('index', 'HotelController@index')
         ->name('agreement-index');


    //-- The coordinator must be logged in to fill the questionair
    Route::get('questionnaire', 'QuestionnaireController@index')
        ->name('questionnaire-index')
        ->middleware('auth');
}

Route::prefix('agreement')
    ->namespace('Agreement')
    ->group(
        function () {
            agreementRoutes();
        }
    );


Route::group(
    ['namespace' => 'Client', 'middleware' => 'auth', 'prefix' => "client"],
    function () {
        Route::get('preferences/index', 'PreferencesController@index')
             ->name('client-preferences-index');
        Route::post('preferences/store', 'PreferencesController@store')
             ->name('client-preferences-store');
    }
);
