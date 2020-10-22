<?php

namespace App\Http\Controllers;

use App\Exceptions\WanderException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Illuminate\Http\Request;
use App\AppUser;

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
            return sendResponse(null, $e->getMessage(), false, $e);
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


}
