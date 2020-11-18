<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Middleware\SwitchLanguageMiddleware;
use App\Http\Middleware\ModelActiveMiddleware;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\VariousController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TravelController;
use App\Http\Controllers\MultiPage;
use App\Http\Controllers\PhotoController;


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
    
    Route::get('/photos/{photo}', [PhotoController::class, 'show']);

    Route::middleware([SwitchLanguageMiddleware::class,ModelActiveMiddleware::class])->group(function () {

        Route::get('/onboarding-items', [MultiPage::class, 'allOnboardingItems'])->name('all-onboarding-item');

        Route::get('/version', [MultiPage::class, 'getVersion']);

        Route::get('/settings', [SettingsController::class, 'filterSettings']);

        Route::get('/pages/{slug}', [MultiPage::class, 'getPage'])->name('single-page');

        Route::middleware(['auth:api'])->group(function () {

            Route::post('/comments', [VariousController::class, 'addComment']);
            
            Route::put('/change-status-friends/{action}', [UserController::class, 'changeFriendRelationshipStatus'])->where('action', 'unmute|mute|unblock|block|delete');

            Route::post('/invitations', [UserController::class, 'sendInvitation']);
            Route::put('/invitations/answer', [UserController::class, 'acceptOrRejectInvitation']);
            Route::post('/travels', [TravelController::class, 'sendHostRequestTravel']);
            Route::post('/travels/{travel_id}/albums', [TravelController::class, 'createAlbum']);
            Route::put('/travels/{travel_id}/albums/{album_id}', [TravelController::class, 'updateAlbum']);
            Route::delete('/travels/{travel_id}/albums/{album_id}/photos', [TravelController::class, 'deleteAlbumPhotos']);
            
            Route::get('/photos/{photo}', [PhotoController::class, 'show']);
        });
    });
});



Route::group(['prefix' => 'auth'], function () {

    Route::middleware([SwitchLanguageMiddleware::class])->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/facebook-login', [AuthController::class, 'facebookLogin']);
        Route::post('/apple-login', [AuthController::class, 'appleLogin']);
        Route::post('/sign-in', [AuthController::class, 'registration']);

        Route::post('/email-verification', [AuthController::class, 'verifyEmail']);
        //Route::post('/password', [AuthController::class, 'updatePassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        Route::post('/recovery-account', [AuthController::class, 'sendEmailRecoveryAccount']);

        Route::get('/me', [AuthController::class, 'me']);
        
        Route::middleware(['auth:api'])->group(function () {

            Route::get('/me/friends', [UserController::class, 'meFriends']);
            Route::get('/me/friends/{id}', [UserController::class, 'meFriend']);
            Route::get('/me/friends-requests', [UserController::class, 'meFriendsRequests']);
            Route::get('/me/profile', [UserController::class, 'meGetProfileInfo']);
            Route::post('/me/profile', [UserController::class, 'meUpdateProfileInfo']);
            Route::get('/me/common-friends/{contact_id}', [UserController::class, 'getCommonFriends']);
            
            
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
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


