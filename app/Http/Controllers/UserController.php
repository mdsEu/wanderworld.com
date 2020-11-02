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
            return sendResponse(null, __('xx:something was wrong'), false, $e);
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
            return sendResponse(null, __('xx:something was wrong'), false, $e);
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
            return sendResponse(null, __('xx:something was wrong'), false, $e);
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
                        throw new WanderException(__('xx:The information of the contact is not enough to do this action.'));
                    }
                    break;
                case 'email':
                    if(!$invitedPhone) {
                        throw new WanderException(__('xx:The information of the contact is not enough to do this action.'));
                    }
                    break;
                case 'facebook':
                    if(!$invitedFacebookId) {
                        throw new WanderException(__('xx:The information of the contact is not enough to do this action.'));
                    }
                    break;
                default:
                    throw new WanderException(__('xx:Action denied.'));
            }
            
            //Validate if already send an invitation to the same user
            $invited = AppUser::findUserByEmailOrPhone($invitedEmail, $invitedPhone);

            $invited_id = null;
            $invitation = null;

            //Validate if user already registered
            if($invited) {
                
                //is it me?
                if($user->id === $invited->id) {
                    throw new WanderException(__('xx:this person is it you?.'));
                }
                //Validate if user rejected me before or pending
                $result = $invited->invitations()
                        ->where('user_id', $user->id)
                        ->whereIn('status', [Invitation::STATUS_PENDING,Invitation::STATUS_REJECTED])
                        ->get();

                if($result->count() > 0) {
                    throw new WanderException(__('xx:you already sent a invitation for this person.'));
                }

                $invited_id = $invited->id;

                $invitation = $invited->invitations()
                        ->where('user_id', $user->id)
                        ->where('status', Invitation::STATUS_CREATED)
                        ->first();
            
            } else {

                $invitation = Invitation::findPendingByEmailOrPhone($user->id, $invitedEmail, $invitedPhone);
                if($invitation) {
                    throw new WanderException(__('xx:you already sent a invitation for this person ;).'));
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
                throw new WanderException(__('xx:It was not posible to create the invitation. Try again.'));
            }

            $sent = $invitation->sendNotification($typeNoti);

            if(!$sent) {
                throw new WanderException(__('xx:something was wrong sending the invitation. Try again later.'));
            }

            $invitation->status = Invitation::STATUS_PENDING;

            if(!$invitation->save()) {
                throw new WanderException(__('xx:something was wrong updating invitation. Try again.'));
            }

            return sendResponse();
        } catch (QueryException $qe) {
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null, __('app.friend_not_found'), false, $notFoundE);
        } catch (WanderException $we) {
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            return sendResponse(null, __('xx:something was wrong'), false, $e);
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
                        throw new WanderException(__('xx:connection error'));
                    }
                    $user->refreshInvitationsContactsForAdding($invitation->user);
                    $invitation->createFriendRelationship();
                    break;
                case 'reject':
                    $invitation->status = Invitation::STATUS_REJECTED;
                    if(!$invitation->save()) {
                        throw new WanderException(__('xx:connection error'));
                    }
                    break;
                default:
                    throw new WanderException(__('xx:Action not valid'));
            }

            $invitation->notifyUsersStatus();

            return sendResponse();
        } catch (QueryException $qe) {
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null, __('xx:invitation not found'), false, $notFoundE);
        } catch (WanderException $we) {
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            return sendResponse(null, __('xx:something was wrong'), false, $e);
        }
    }
}
