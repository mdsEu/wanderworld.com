<?php

namespace App\Http\Controllers;

use App\Exceptions\WanderException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

use App\Models\Comment;
use App\Models\AppUser;

use JWTAuth;


class VariousController extends Controller
{

    public function __construct()
    {
        //$this->middleware('auth:api', ['except' => ['login']]);
        $this->guard = 'api';
    }

    /**
     * Create a user comment
     */
    public function addComment(Request $request) {
        try {

            $params = $request->only([
                'message',
            ]);

            $validator = Validator::make($params, [
                'message' => 'required|max:240',
            ]);

            if ($validator->fails()) {
                return sendResponse(null,$validator->messages(),false);
            }

            $user = auth($this->guard)->user();

            $lastComment = $user->comments->last();

            if($lastComment) {
                $createdAt = Carbon::createFromFormat('Y-m-d H:i:s', $lastComment->created_at);
                $now = Carbon::now('UTC');
                
                $limitHoursSendComment = intval(setting('admin.limit_hours_send_comment', 1));
                
                if ($createdAt->diffInHours($now) < $limitHoursSendComment) {
                    throw new WanderException(__('app.user_comment_earlier',['limit' => $limitHoursSendComment]));
                }
            }

            $comment = new Comment();

            $comment->message = $params['message'];
            $comment->user_id = $user->id;

            if(!$comment->save()) {
                throw new WanderException(__('app.user_comment_not_received'));
            }

            return sendResponse();
            
        } catch (QueryException $qe) {
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null, __('app.data_not_found'), false, $notFoundE);
        } catch (WanderException $we) {
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            return sendResponse(null, __('app.something_was_wrong'), false, $e);
        }
    }


    /**
     * Search country connections
     */
    public function searchCountryConnections(Request $request) {
        try {

            $needle = $request->get('needle', null);

            if (!$needle) {
                return sendResponse([]);
            }

            $user = auth($this->guard)->user();

            $input = trim($needle);

            $sessionToken = Str::random(20);
            $lang = app()->getLocale();
            $googleKey = setting('admin.google_maps_key', env('GOOGLE_KEY', ''));



            $countries = readJsonCountries();

            $results = [];


            
            $response = Http::get("https://maps.googleapis.com/maps/api/place/autocomplete/json?input=$input&types=(regions)&sessiontoken=$sessionToken&key=$googleKey&language=$lang");
    
            if(!$response->successful()) {
                throw new WanderException(__('app.connection_error'));
            }
            $arrayData = $response->json();

            if($arrayData['status'] !== 'OK') {
                return sendResponse([]);
            }

            $predictions = $arrayData['predictions'];

            $listUsersFound = collect([]);

            foreach($predictions as $predi) {
                if( in_array('country', $predi['types']) ) {

                    $indexFoundCountry = findInArray($predi['place_id'],$countries,'place_id');

                    if($indexFoundCountry === false) {
                        continue;
                    }

                    $foundCountry = $countries[$indexFoundCountry];

                    $usersFound = $user->activeFriendsLevel( 2 )->where('country_code', $foundCountry['country_code'])
                                                                ->whereNotIn('id', $listUsersFound->pluck('id'));
                    if($usersFound->count() > 0) {
                        $listUsersFound = $listUsersFound->merge($usersFound);
                    }
                }
            }

            $returList = [];
            foreach($listUsersFound as $appUser) {
                $idxFound = \findInArray($appUser->city_gplace_id, $returList, 'city_gplace_id');
                if($idxFound === false) {
                    $countryObj = new \stdClass;
                    $countryObj->amount = 1;
                    $countryObj->country_code = $appUser->country_code;
                    $countryObj->city_gplace_id = $appUser->city_gplace_id;
                    $countryObj->city_name = $appUser->city_name;
                    $countryObj->country_name = $appUser->country_name;
                    $returList[] = $countryObj;
                } else  {
                    $returList[$idxFound]->amount = $returList[$idxFound]->amount + 1;
                }
            }

            return sendResponse($returList);
        } catch (QueryException $qe) {
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null, __('app.data_not_found'), false, $notFoundE);
        } catch (WanderException $we) {
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            return sendResponse(null, __('app.something_was_wrong'), false, $e);
        }
    }
}
