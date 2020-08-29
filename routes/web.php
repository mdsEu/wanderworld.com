<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Middleware\SwitchLanguageMiddleware;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::middleware([SwitchLanguageMiddleware::class])->group(function () {
    Route::get('/app/{screen}', function (Request $request, $screen) {

        return view('app',array(
            'screen' => $screen,
        ));
    });
});


Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();
});
