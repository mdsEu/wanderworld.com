<?php

namespace App\Http\Controllers;

use App\Exceptions\WanderException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Illuminate\Http\Request;
use App\Models\AppUser;
use App\Models\Invitation;

use JWTAuth;


class UserController extends Controller
{
    public function __construct()
    {
        //$this->middleware('auth:api', ['except' => ['login']]);
        $this->guard = 'api';
    }


    public function authenticate(Request $request) {
        return null;
    }

    public function meFriends(Request $request) {
        try {

            $user = JWTAuth::parseToken()->authenticate();

            $friendsLimit = intval(setting('admin.friends_list_limit', 20));

            return sendResponse($user->friends()->paginate($friendsLimit));
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
     * 
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
            $invitedPhone = $request->get('phone', null);
            $invitedFacebookId = $request->get('facebook_id', null);
            

            switch ($typeNoti) {
                case 'sms':
                    if(!$invitedPhone) {
                        throw new WanderException(__('app.contact_info_enough'));
                    }
                    break;
                case 'email':
                    if(!$invitedPhone) {
                        throw new WanderException(__('app.contact_info_enough'));
                    }
                    break;
                case 'facebook':
                    if(!$invitedFacebookId) {
                        throw new WanderException(__('app.contact_info_enough'));
                    }
                    break;
                default:
                    throw new WanderException(__('app.action_denied'));
            }
            
            //Validate if already send an invitation to the same user
            $invited = AppUser::findUserByEmailOrPhone($invitedEmail, $invitedPhone);

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
                    throw new WanderException(__('app.already_send_invitation'));
                }

                $invited_id = $invited->id;

                $invitation = $invited->invitations()
                        ->where('user_id', $user->id)
                        ->where('status', Invitation::STATUS_CREATED)
                        ->first();
            
            } else {

                $invitation = Invitation::findPendingByEmailOrPhone($user->id, $invitedEmail, $invitedPhone);
                if($invitation) {
                    throw new WanderException(__('app.already_send_invitation'));
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
                    //'invited_fbid' => $invitedFacebookId,
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
}
