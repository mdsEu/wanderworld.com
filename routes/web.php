<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Middleware\SwitchLanguageMiddleware;
use Illuminate\Support\Facades\Http;
use App\Exceptions\WanderException;

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
            'screen' => $screen.(!empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : ''),
        ));
    });
});


Route::group(['prefix' => 'admin'], function () {
    Voyager::routes();
});


Route::middleware([SwitchLanguageMiddleware::class])->get('/app-facebook-login', function () {
    return view('appfacebooklogin');
});