<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Middleware\SwitchLanguageMiddleware;
use App\Http\Middleware\ModelActiveMiddleware;


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

Route::group(['prefix' => 'services/v1/'], function () {

    Route::middleware([SwitchLanguageMiddleware::class,ModelActiveMiddleware::class])->group(function () {

        Route::get('/onboarding-items', 'MultiPage@allOnboardingItems')->name('all-onboarding-item');

        Route::get('/version', 'MultiPage@getVersion');
    });
});



Route::group(['prefix' => 'auth'], function () {

    Route::middleware([SwitchLanguageMiddleware::class])->group(function () {
        Route::post('/login', 'AuthController@login')->name('login');
        Route::post('/facebook-login', 'AuthController@facebookLogin');
        Route::post('/me', 'AuthController@me');

        Route::middleware(['auth:api'])->group(function () {
            Route::post('/logout', 'AuthController@logout');
            Route::post('/refresh', 'AuthController@refresh');
        });

    });
});
/*
Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function ($router) {



});
*/


