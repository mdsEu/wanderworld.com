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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['prefix' => 'wanderworld/services/v1/'], function () {

    Route::middleware([SwitchLanguageMiddleware::class,ModelActiveMiddleware::class])->group(function () {

        Route::get('/onboarding-items', 'MultiPage@allOnboardingItems')->name('all-onboarding-item');

        /*Route::get('admin/profile', function () {
            //
        })->withoutMiddleware([SwitchLanguageMiddleware::class]);*/
    });
});



