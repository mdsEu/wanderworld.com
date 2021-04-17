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
    Route::get('/users/{user_id}/avatar', [UserController::class, 'showAvatar']);

    Route::middleware([SwitchLanguageMiddleware::class,ModelActiveMiddleware::class])->group(function () {

        Route::get('/onboarding-items', [MultiPage::class, 'allOnboardingItems'])->name('all-onboarding-item');

        Route::get('/version', [MultiPage::class, 'getVersion']);

        Route::get('/settings', [SettingsController::class, 'filterSettings']);

        Route::get('/pages/{slug}', [MultiPage::class, 'getPage'])->name('single-page');
        Route::get('/faqs', [MultiPage::class, 'getFaqs'])->name('all-faqs');

        Route::middleware(['auth:api'])->group(function () {

            Route::get('/users', [UserController::class, 'getAllAppUsers']);

            Route::post('/comments', [VariousController::class, 'addComment']);
            
            Route::put('/change-status-friends/{action}', [UserController::class, 'changeFriendRelationshipStatus'])->where('action', 'unmute|mute|unblock|block|delete');

            Route::post('/invitations', [UserController::class, 'sendInvitation']);
            Route::put('/invitations/answer', [UserController::class, 'acceptOrRejectInvitation']);
            Route::post('/travels', [TravelController::class, 'sendHostRequestTravel']);

            Route::get('/finished-travels', [TravelController::class, 'getUserFinishedTravels']);
            Route::get('/schedule-travels', [TravelController::class, 'getUserScheduleTravels']);
            Route::get('/accepted-travels', [TravelController::class, 'getUserAcceptedTravels']);
            Route::get('/requests-travels', [TravelController::class, 'getUserRequestsTravels']);
            
            
            
            Route::get('/travels/{travel_id}', [TravelController::class, 'getUserTravel']);
            Route::post('/travels/{travel_id}/albums', [TravelController::class, 'createAlbum']);
            Route::post('/travels/{travel_id}/albums/{album_id}', [TravelController::class, 'updateAlbum']);
            Route::delete('/travels/{travel_id}/albums/{album_id}/photos', [TravelController::class, 'deleteAlbumPhotos']);
            Route::get('/travels/{travel_id}/albums/{album_id}/photos', [TravelController::class, 'getAlbumPhotos']);
            Route::post('/travels/{travel_id}/albums/{album_id}/photo', [TravelController::class, 'uploadOnePhotoAlbum']);
            
            Route::get('/notifications-counters', [TravelController::class, 'countersNotifications']);

            Route::post('/travels/{travel_id}/change-status', [TravelController::class, 'changeTravelStatus']);
            Route::put('/travels/{travel_id}/change-dates', [TravelController::class, 'changeTravelDates']);

            Route::post('/travels/{travel_id}/recommendations', [TravelController::class, 'createRecommendation']);

            Route::get('/friends/{friend_id}/profile', [UserController::class, 'getFriendProfileInfo']);
            Route::get('/friends/{friend_id}/finished-travels', [UserController::class, 'getFriendFinishedTravels']);


            Route::post('/search-connections', [VariousController::class, 'searchCountryConnections']);
            Route::post('/facebook-friends', [VariousController::class, 'facebookFriends']);
            Route::get('/friends-contacts', [UserController::class, 'getFriendsContacts']);
            Route::get('/interests', [VariousController::class, 'getInterests']);
        });
    });
});



Route::group(['prefix' => 'auth'], function () {

    Route::middleware([SwitchLanguageMiddleware::class])->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/facebook-login', [AuthController::class, 'facebookLogin']);
        Route::post('/apple-login/{identitytoken}', [AuthController::class, 'appleLogin']);
        Route::post('/sign-in', [AuthController::class, 'registration']);

        Route::post('/email-verification', [AuthController::class, 'verifyEmail']);
        
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
        Route::post('/recovery-account', [AuthController::class, 'sendEmailRecoveryAccount']);

        
        Route::middleware(['auth:api'])->group(function () {
            
            Route::get('/me', [AuthController::class, 'me']);

            Route::get('/me/friends-cid', [UserController::class, 'meFriendsChatLogins']);
            Route::get('/me/friends-filter-chat-ids', [UserController::class, 'meFriendsChatFilterIds']);

            Route::get('/me/friends', [UserController::class, 'meFriends']);
            Route::get('/me/friends/{id}', [UserController::class, 'meFriend']);
            Route::get('/me/friends-requests', [UserController::class, 'meFriendsRequests']);
            Route::get('/me/profile', [UserController::class, 'meGetProfileInfo']);
            Route::post('/me/profile', [UserController::class, 'meUpdateProfileInfo']);
            Route::post('/me/remove-avatar', [UserController::class, 'meRemoveAvatar']);
            Route::post('/me/change-city', [UserController::class, 'meUpdateUserCityInfo']);
            
            Route::get('/me/common-friends/{contact_id}', [UserController::class, 'getCommonFriends']);
            Route::get('/me/blocked-friends', [UserController::class, 'getBlockedFriends']);
            Route::get('/me/visit-recommendations', [UserController::class, 'getVisitRecommendations']);

            Route::get('/me/friends-level2', [UserController::class, 'getFriendsUntilLevel2']);
            Route::get('/me/friends-level2/reduced', [UserController::class, 'getFriendsUntilLevel2']);


            Route::post('/me/report-image', [PhotoController::class, 'reportImage']);
            
            Route::get('/me/markers-map', [VariousController::class, 'markersMap']);
            Route::get('/me/markers-map-continents', [VariousController::class, 'markersMapContinents']);

            Route::put('/me/change-password', [AuthController::class, 'updatePassword']);
            Route::get('/me/counter-friends-invitations', [UserController::class, 'getNumberOfFriendRelationshipInvitations']);
            
            
            
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


