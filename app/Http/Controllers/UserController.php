<?php

namespace App\Http\Controllers;

use App\Exceptions\WanderException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\Response;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Models\AppUser;
use App\Models\Invitation;
use App\Models\Recommendation;

use JWTAuth;


class UserController extends Controller
{

    public $guard;

    public function __construct()
    {
        //$this->middleware('auth:api', ['except' => ['login']]);
        $this->guard = 'api';
    }


    public function authenticate(Request $request) {
        return null;
    }

    /**
     * 
     */
    public function showAvatar(Request $request, $user_id) {
        try {
            $token = $request->get('token', null);

            if(!$token) {
                return \abort(Response::HTTP_UNAUTHORIZED);
            }
            $user = auth($this->guard)->setToken($token)->user();

            if(!$user) {
                return \abort(Response::HTTP_UNAUTHORIZED);
            }
            return AppUser::findOrFail($user_id)->showAvatar();
        } catch (\Exception $e) {
            return \abort(Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * Return user's friends
     * (Paginated)
     */
    public function meFriends(Request $request) {
        try {

            $user = JWTAuth::parseToken()->authenticate();

            $friendsLimit = intval(setting('admin.friends_list_limit', 20));

            return sendResponse($user->activeFriends()->paginate($friendsLimit));
        } catch (QueryException $qe) {
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null, __('app.friend_not_found'), false, $notFoundE);
        } catch (WanderException $we) {
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            return sendResponse(null, __('app.something_was_wrong'), false, $e);
        }
    }

    /**
     * Return user's friends logins (Chat)
     * (Paginated)
     */
    public function meFriendsChatLogins(Request $request) {
        try {
            $user = auth()->user();
            return sendResponse($user->friends()->get()->pluck('chat_user_id'));
        } catch (QueryException $qe) {
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null, __('app.friend_not_found'), false, $notFoundE);
        } catch (WanderException $we) {
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            return sendResponse(null, __('app.something_was_wrong'), false, $e);
        }
    }
    

    /**
     * Return single user logged friend
     */
    public function meFriend(Request $request, $friend_id) {
        try {

            $me = JWTAuth::parseToken()->authenticate();

            $friend = $me->friends->find($friend_id);

            if(is_null($friend)) {
                throw new WanderException(__('app.friend_not_found'));
            }

            return sendResponse($friend);
        } catch (QueryException $qe) {
            return sendResponse( null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null, __('app.friend_not_found'), false, $notFoundE);
        } catch (WanderException $we) {
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            return sendResponse(null, $e->getMessage(), false, $e);
        }
    }

    /**
     * Return user logged pending invitations to be friends.
     * (Paginated)
     */
    public function meFriendsRequests(Request $request) {
        try {

            $user = JWTAuth::parseToken()->authenticate();

            $friendsLimit = intval(setting('admin.friends_list_limit', 20));

            return sendResponse($user->pendingInvitations()->with('user')->paginate($friendsLimit));
        } catch (QueryException $qe) {
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null, __('app.friend_not_found'), false, $notFoundE);
        } catch (WanderException $we) {
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            return sendResponse(null, __('app.something_was_wrong'), false, $e);
        }
    }

    /**
     * Change a friend relationship status
     */
    public function changeFriendRelationshipStatus(Request $request, $action) {
        try {

            $user = JWTAuth::parseToken()->authenticate();
            $friend_id = $request->get('friend', null);

            $friend = AppUser::findOrFail($friend_id);
            
            $user->updateFriendRelationship($action, $friend->id);

            return sendResponse();
        } catch (QueryException $qe) {
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null, __('app.friend_not_found'), false, $notFoundE);
        } catch (WanderException $we) {
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            return sendResponse(null, __('app.something_was_wrong'), false, $e);
        }
    }


    /**
     * Function to send Invitation
     */
    public function sendInvitation(Request $request) {
        

        try {

            $user = JWTAuth::parseToken()->authenticate();

            $typeNoti = $request->get('type_notification', 'sms');

            $invitedEmail = $request->get('email', null);
            $invitedPhone = sanitizePhone($request->get('phone', null));
            $invitedFacebookId = $request->get('facebook_id', null);

            $invitedFriendLevel2Id = $request->get('friend_level2_id', null);
            

            switch ($typeNoti) {
                case 'sms':
                    if(!$invitedPhone) {
                        throw new WanderException(__('app.contact_info_enough'));
                    }
                    break;
                case 'email':
                    if(!$invitedEmail) {
                        throw new WanderException(__('app.contact_info_enough'));
                    }
                    break;
                case 'facebook':
                    if(!$invitedFacebookId) {
                        throw new WanderException(__('app.contact_info_enough'));
                    }
                    break;
                case 'level2':
                    if(!$invitedFriendLevel2Id) {
                        throw new WanderException(__('app.contact_info_enough'));
                    }
                    $invitedEmail = AppUser::findOrFail($invitedFriendLevel2Id)->email;
                    break;
                default:
                    throw new WanderException(__('app.action_denied'));
            }
            
            //Validate if already send an invitation to the same user
            $invited = AppUser::findPendingByEmailOrPhoneOrFbid($invitedEmail, $invitedPhone, $invitedFacebookId);

            $invited_id = null;
            $invitation = null;

            //Validate if user already registered
            if($invited) {
                
                //is it me?
                if($user->id === $invited->id) {
                    throw new WanderException(__('app.is_it_you'));
                }
                //Validate if user rejected me before or pending
                $result = $invited->invitations()
                        ->where('user_id', $user->id)
                        ->whereIn('status', [Invitation::STATUS_PENDING,Invitation::STATUS_REJECTED])
                        ->get();

                if($result->count() > 0) {
                    return sendResponse(null, __('app.already_send_invitation'));
                }

                $invited_id = $invited->id;

                $invitation = $invited->invitations()
                        ->where('user_id', $user->id)
                        ->where('status', Invitation::STATUS_CREATED)
                        ->first();
            
            } else {

                $invitation = Invitation::findPendingByEmailOrPhoneOrFbid($user->id, $invitedEmail, $invitedPhone, $invitedFacebookId);
                if($invitation) {
                    return sendResponse(null, __('app.already_send_invitation'));
                }

            }
            
            if(!$invitation) {
                $infoInvi = new \stdClass();
                $infoInvi->numberContacts = 0;
                $invitation = Invitation::create([
                    'user_id' => $user->id,
                    'invited_id' => $invited_id,
                    'invited_email' => $invitedEmail,
                    'invited_phone' => $invitedPhone,
                    'invited_fbid' => $invitedFacebookId,
                    'invited_info' => json_encode($request->get('info',$infoInvi)),
                    'status' => Invitation::STATUS_CREATED,
                ]);
            }
            
            
            if(!$invitation) {
                throw new WanderException(__('app.no_posible_send_invitation'));
            }

            $sent = $invitation->sendNotification($typeNoti);

            if(!$sent) {
                throw new WanderException(__('app.no_posible_send_invitation'));
            }

            $invitation->status = Invitation::STATUS_PENDING;

            if(!$invitation->save()) {
                throw new WanderException(__('app.no_posible_send_invitation'));
            }

            return sendResponse();
        } catch (QueryException $qe) {
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null, __('app.friend_not_found'), false, $notFoundE);
        } catch (WanderException $we) {
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            return sendResponse(null, __('app.something_was_wrong'), false, $e);
        }
    }


    /**
     * Function to send Accept or Reject an Friend relationship invitation
     */
    public function acceptOrRejectInvitation(Request $request) {
        
        try {

            $user = JWTAuth::parseToken()->authenticate();

            $answer = $request->get('answer', null);

            $invitation_id = $request->get('invitation', null);
            
            $invitation = Invitation::findOrFail($invitation_id);

            switch ($answer) {
                case 'accept':
                    $invitation->status = Invitation::STATUS_ACCEPTED;
                    if(!$invitation->save()) {
                        throw new WanderException(__('app.connection_error'));
                    }
                    $user->refreshInvitationsContactsForAdding($invitation->user);
                    $invitation->createFriendRelationship();
                    break;
                case 'reject':
                    $invitation->status = Invitation::STATUS_REJECTED;
                    if(!$invitation->save()) {
                        throw new WanderException(__('app.connection_error'));
                    }
                    break;
                default:
                    throw new WanderException(__('app.action_not_valid'));
            }

            $invitation->notifyUsersStatus();

            return sendResponse();
        } catch (QueryException $qe) {
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null, __('app.invitation_not_found'), false, $notFoundE);
        } catch (WanderException $we) {
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            return sendResponse(null, __('app.something_was_wrong'), false, $e);
        }
    }


    /**
     * Function to update public user profile data
     */
    public function meUpdateProfileInfo(Request $request) {
        try {


            $isPublic = $request->get('public', 'public') === 'public';

            \logActivity($request->all());
            
            $params = $request->only($isPublic ? [
                'name',
                'image',
                'aboutme',
                'interests',
                'languages',
            ] : [
                'birthday',
                'city',
                'cellphone',
                'gender',
                'personal_status',
            ]);

            $rules = $isPublic ? [
                'name' => 'required|max:20',
                'image' => 'image|mimes:jpeg,png,jpg|max:2048',
                'aboutme' => 'required|max:300',
                'interests' => 'required|array|max:15',
                'languages' => 'required|array|max:6',
            ] : [
                'birthday' => [
                    function ($attribute, $value, $fail) {
                        $minAge = intval(env('MIN_AGE_REGISTRATION', 18));

                        if (!preg_match('/^\d{4}-\d{2}-\d{2}/',$value)) {
                            $fail(__('validation.min_age_required', ['age' => $minAge]));
                            return;
                        }
                        $age = Carbon::createFromFormat('Y-m-d',$value)->age;

                        if ($age < $minAge) {
                            $fail(__('validation.min_age_required', ['age' => $minAge]));
                            return;
                        }
                    }
                ],
                'gender' => [
                    'required',
                    Rule::in(['male','female','other']),
                ],
                'personal_status' => [
                    'required',
                    Rule::in(['single','married','inrelation']),
                ],
                'city.name' => 'required',
                'city.place_id' => 'required',
                'city.country.name' => 'required',
                'city.country.code' => [
                    'required',
                    Rule::in(LIST_COUNTRYS_CODES),
                ],
                'cellphone.dial' => 'required',
                'cellphone.number' => 'required',
            ] ;

            $validator = Validator::make($params, $rules);

            if ($validator->fails()) {
                return sendResponse(null,$validator->messages(),false);
            }

            DB::beginTransaction();

            $user = auth($this->guard)->user();

            if($isPublic) {

                $user->name = $params['name'];
                if($request->file('image')) {
                    $user->avatar = $request->file('image')->store('avatars', [
                        'disk' => config('voyager.storage.disk'),
                        'visibility' => 'public',
                    ]);
                }
                
                $user->updateMetaValue('about_me', $params['aboutme']);
                $user->updateMetaValue('is_aboutme_private', $request->get('is_aboutme_private', 'no'));

                $user->updateMetaValue('my_interests', $params['interests']);
                $user->updateMetaValue('is_interests_private', $request->get('is_interests_private', 'no'));

                $user->updateMetaValue('my_languages', $params['languages']);
                $user->updateMetaValue('is_languages_private', $request->get('is_languages_private', 'no'));

                $user->updateMetaValue('info_public_saved', 'yes');
                
                
            } else {


                $countries = readJsonCountries();

                $idxFoundCountry = findInArray($params['city']['country']['code'], $countries, 'country_code');

                if ($idxFoundCountry === false) {
                    //Never will happen. Previously validated....
                }
                $foundCountry = $countries[$idxFoundCountry];

                $user->continent_code = $foundCountry['continent_code'];
                $user->country_code = $foundCountry['country_code'];

                $new_gplace_id = trim($params['city']['place_id']);
                $timesChangeCity = $user->getTimesChangeCity();
                $limitChangeCity = intval( $user->getMetaValue('limit_change_city', setting('admin.limit_change_city', 2)) );
                if ($user->city_gplace_id !== $new_gplace_id && $timesChangeCity >= $limitChangeCity) {
                    DB::rollback();
                    return sendResponse(null,['city' => __('app.reached_city_change_limit')],false);
                }

                if ($user->city_gplace_id !== $new_gplace_id) {
                    $user->updateMetaValue(Carbon::now('UTC')->format('YYYY').'_times_change_city', $timesChangeCity + 1);
                    $user->city_gplace_id = $new_gplace_id;
                }

                $user->refreshCityName();
                $user->updateMetaValue('is_city_private', $request->get('is_city_private', 'no'));

                $user->updateMetaValue('birthday', $params['birthday']);
                $user->updateMetaValue('is_birthday_private', $request->get('is_birthday_private', 'no'));

                $phone = sanitizePhone($params['cellphone']['dial'].$params['cellphone']['number']);
                $user->updateMetaValue('phone', $phone);
                $user->updateMetaValue('phone_dial', $params['cellphone']['dial']);
                $user->updateMetaValue('phone_number', $params['cellphone']['number']);
                $user->updateMetaValue('is_phone_private', $request->get('is_phone_private', 'no'));
                $user->updateMetaValue('gender', $params['gender']);
                $user->updateMetaValue('is_gender_private', $request->get('is_gender_private', 'no'));
                $user->updateMetaValue('personal_status', $params['personal_status']);
                $user->updateMetaValue('is_personal_status_private', $request->get('is_personal_status_private', 'no'));

                $user->updateMetaValue('is_email_private', $request->get('is_email_private', 'no'));

                $user->updateMetaValue('info_private_saved', 'yes');

            }

            if (!$user->save()) {
                throw new WanderException(__('app.connection_error'));
            }

            $user->updateChatDataAccount();

            DB::commit();

            return sendResponse();
        } catch (QueryException $qe) {
            DB::rollback();
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            DB::rollback();
            return sendResponse(null, __('app.data_not_found'), false, $notFoundE);
        } catch (WanderException $we) {
            DB::rollback();
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            DB::rollback();
            return sendResponse(null, __('app.something_was_wrong'), false, $e);
        }
    }

    /**
     * Function to get the profile information
     */
    public function meGetProfileInfo(Request $request) {
        try {
            $user = auth($this->guard)->user();
            return sendResponse($user->getProfileInfo());
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
     * Function to get the friend profile information
     */
    public function getFriendProfileInfo(Request $request, $friend_id) {
        try {
            $user = auth($this->guard)->user();
            $friend = AppUser::findOrFail($friend_id);
            $info = $friend->getProfileInfo();
            $info->common_contacts = $user->getCommonContacts($friend);
            return sendResponse($info);
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
     * Return common friend of the user logged with other user
     */
    public function getCommonFriends(Request $request, $contact_id) {
        try {
            $user = auth($this->guard)->user();

            $contactUser = AppUser::findOrFail($contact_id);

            $myFriendsIds = $user->activeFriendsLevel( 2 )->pluck('id');
            $commons = $contactUser->activeFriendsLevel( 2 )->whereIn('id',$myFriendsIds);

            return sendResponse($commons->values());
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
     * Return user visits recommended travels
     */
    public function getVisitRecommendations(Request $request) {
        try {
            $user = auth($this->guard)->user();
            $recommendationsLimit = intval(setting('admin.recommendations_list_limit', 20));

            $modelUser = AppUser::with(['visitRecommendations' => function($relationTravel){
                $relationTravel->with('user');
                $relationTravel->with('invited');
                $relationTravel->with('travel.host');
            }])->find($user->id);

            return sendResponse(getPaginate($modelUser->visitRecommendations, $recommendationsLimit));
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
     * Return user's friends nesting friends of my friends
     */
    public function getFriendsUntilLevel2(Request $request) {
        try {
            $user = auth($this->guard)->user();
            
            $friendsLimit = intval(setting('admin.friends_list_limit', 20));

            $friends = $user->activeFriendsLevel( 2 );

            return sendResponse(getPaginate($friends, $friendsLimit));
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
     * Retun friend finished travel with minimal photo information
     */
    public function getFriendFinishedTravels(Request $request, $friend_id) {
        try {
            $user = auth($this->guard)->user();

            
            $friend = AppUser::with(['finishedTravels' => function($relationTravel){
                $relationTravel->with('activeAlbums.activePhotos');
            }])->findOrFail($friend_id);
            return sendResponse($friend->finishedTravels);
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
