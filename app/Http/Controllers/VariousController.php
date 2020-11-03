<?php

namespace App\Http\Controllers;

use App\Exceptions\WanderException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

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

            $user = JWTAuth::parseToken()->authenticate();

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
            return sendResponse(null, $e->getMessage(), false, $e);
        }
    }
}
