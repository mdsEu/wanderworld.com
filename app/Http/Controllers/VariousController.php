<?php

namespace App\Http\Controllers;

use App\Exceptions\WanderException;
use App\Exceptions\ChatException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

use App\Models\Comment;
use App\Models\AppUser;
use App\Models\Interest;
use App\Models\ChatFile;
use App\Mail\GenericMail;

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
                'comment',
            ]);

            $validator = Validator::make($params, [
                'comment' => 'required|max:240',
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

            $comment->message = $params['comment'];
            $comment->user_id = $user->id;

            if(!$comment->save()) {
                throw new WanderException(__('app.user_comment_not_received'));
            }

            $receivers = setting('admin.comments_receiver_email', '');

            if($receivers) {
                $receivers = \explode(',', $receivers);
                $button = array(
                    'link' => secure_url("/admin/comments/{$comment->id}"),
                    'text' => __('notification.see_comment'),
                );
    
                sendMail((new GenericMail(
                    __('notification.new_comment'),
                    __('notification.new_comment_details'),
                    $button
                ))->subject(__('notification.subject_new_comment'))
                    ->to($receivers));
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

            $sessionToken = $request->get('session_token', Str::random(20));
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
                $foundCountry = null;

                if( in_array('country', $predi['types']) ) {

                    $indexFoundCountry = findInArray($predi['place_id'],$countries,'place_id');

                    if($indexFoundCountry === false) {
                        continue;
                    }
                    $foundCountry = $countries[$indexFoundCountry];
                } else if(in_array('political', $predi['types']) && in_array('locality', $predi['types'])) {//is city?
                    $foundCountry = ggetCountryOfPlaceId($predi['place_id']);
                }

                if(!$foundCountry) {
                    continue;
                }

                $usersFound = $user->activeFriendsLevel( 2 )->where('country_code', $foundCountry['country_code'])
                                                                ->whereNotIn('id', $listUsersFound->pluck('id'));
                if($usersFound->count() > 0) {
                    $listUsersFound = $listUsersFound->merge($usersFound);
                }
            }

            $collectResults = collect([]);
            foreach($listUsersFound as $appUser) {
                $userInfo = new \stdClass;

                $userInfo->id = $appUser->id;
                $userInfo->name = $appUser->name;
                $userInfo->level = $appUser->level;
                $userInfo->country_code = $appUser->country_code;
                $userInfo->city_gplace_id = $appUser->city_gplace_id;
                $userInfo->city_name = $appUser->city_name;
                $userInfo->country_name = $appUser->country_name;
                $userInfo->slug_city = $appUser->slug_city;
                $userInfo->chat_user_id = $appUser->chat_user_id;
                $userInfo->number_commons = $user->getCommonContacts($appUser)->count();
                
                $userInfo->has_any_travel = $user->hasAnyFinishedTravel($appUser);

                $collectResults->push($userInfo);
            }

            $grouped = $collectResults->groupBy('slug_city');

            $grouped->all();

            return sendResponse(collect($grouped)->sortDesc()->values());
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
     * Simplified friends for maps (markers)
     */
    public function markersMap(Request $request) {
        try {

            
            $user = auth($this->guard)->user();

            $lang = app()->getLocale();

            $friendsGrouped = $user->activeFriendsLevel( 2 )->groupBy('country_code');

            $collectResults = collect([]);
            foreach($friendsGrouped as $code=>$arrUsers) {
                $countryGroup = new \stdClass;

                $countryGroup->country_code = $code;
                $countryGroup->number = count($arrUsers);
                
                $collectResults->push($countryGroup);
            }

            return sendResponse($collectResults);
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
     * Simplified friends for maps (markers)
     */
    public function markersMapContinents(Request $request) {
        try {

            
            $user = auth($this->guard)->user();

            $lang = app()->getLocale();

            $friendsGrouped = $user->activeFriendsLevel( 2 )->groupBy('continent_code');

            $collectResults = collect([]);
            foreach($friendsGrouped as $code=>$arrUsers) {
                $continentGroup = new \stdClass;

                $continentGroup->continent_code = $code;
                $continentGroup->number = count($arrUsers);
                
                $collectResults->push($continentGroup);
            }

            return sendResponse($collectResults);
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

    public function facebookFriends(Request $request) {
        try {
            $user = auth($this->guard)->user();
            
            $FB_GRAPH_VERSION = '7.0';
    
            $token = $request->get('token', "");
    
            $response = Http::get("https://graph.facebook.com/v$FB_GRAPH_VERSION/me/friends?fields=id,name,email&access_token=$token");
        
            if(!$response->successful()) {
                throw new WanderException(__('app.connection_error'));
            }
            $arrayData = $response->json();
            $list = [];
            foreach($arrayData['data'] as $friend) {
                $foundUser = AppUser::findUserByFacebookId($friend['id']);

                if($foundUser) {
                    makeFriendRelationship($user, $foundUser);
                }
                $userFb = $user->getFriendByFacebookId($friend['id']);
                $itemFriend = array(
                    'id' => $friend['id'],
                    'name' => $friend['name'],
                );
                if($userFb) {
                    $itemFriend['user_id'] = $userFb->id;
                    $itemFriend['chat_user_id'] = $userFb->chat_user_id;
                    $itemFriend['has_any_travel'] = $user->hasAnyFinishedTravel($userFb);
                }
                $list[] = $itemFriend;
            }

            return sendResponse($list);
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

    
    public function getInterests(Request $request) {
        try {
            return sendResponse( Interest::withTranslations()->get() );
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
     * Upload chat file
     */
    public function uploadChatFile(Request $request) {
        try {

            
            $token = $request->get('token', null);

            if(!$token) {
                return \abort(Response::HTTP_UNAUTHORIZED);
            }
            $user = auth($this->guard)->setToken($token)->user();

            if(!$user) {
                return \abort(Response::HTTP_UNAUTHORIZED);
            }

            $params = $request->only([
                'file',
            ]);

            $sizeKb = setting('admin.file_size_limit', 2048);
            $rules = [
                'file' => 'file|mimes:xlxs,docx,pdf,svg,jpeg,png,jpg|max:'.$sizeKb,
            ];

            $validator = Validator::make($params, $rules);

            if ($validator->fails()) {
                return sendResponse(null,$validator->messages(),false);
            }
            $disk = config('voyager.storage.disk');
            $uploadedFile = $request->file('file');
            $path = $uploadedFile->store('chat-files', [
                'disk' => $disk,
                'visibility' => 'public',
            ]);

            $myChatFile = new ChatFile();

            $myChatFile->path = $path;
            $myChatFile->disk = $disk;
            $myChatFile->mime = $uploadedFile->getMimeType();

            
            if(!$myChatFile->save()) {
                throw new WanderException(__('app.connection_error'));
            }

            return sendResponse($myChatFile->id);
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
     * Url chat file
     */
    public function showChatFile(Request $request, ChatFile $chatFile) {
        try {

            
            $token = $request->get('token', null);

            if(!$token) {
                return \abort(Response::HTTP_UNAUTHORIZED);
            }
            $user = auth($this->guard)->setToken($token)->user();

            if(!$user) {
                return \abort(Response::HTTP_UNAUTHORIZED);
            }

            return $chatFile->show();
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
