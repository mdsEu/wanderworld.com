<?php

namespace App\Http\Controllers;

use App\Exceptions\WanderException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Symfony\Component\HttpFoundation\Response;
use TCG\Voyager\Facades\Voyager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use App\Models\AppUser;
use App\Models\Invitation;
use App\Models\Travel;
use App\Models\Photo;
use App\Models\Album;
use App\Models\ImageReport;

class PhotoController extends Controller
{
    public $guard;

    public function __construct()
    {
        //$this->middleware('auth:api', ['except' => ['login']]);
        $this->guard = 'api';
    }

    /**
     * 
     */
    public function show(Request $request, Photo $photo) {
        try {
            $token = $request->get('token', null);

            if(!$token) {
                return \abort(Response::HTTP_UNAUTHORIZED);
            }
            $user = auth($this->guard)->setToken($token)->user();

            if(!$user) {
                return \abort(Response::HTTP_UNAUTHORIZED);
            }
            return $photo->show();
        } catch (\Exception $e) {
            return \abort(Response::HTTP_UNAUTHORIZED);
        }
    }
    
    /**
     * Function to get the friend profile information
     */
    public function reportImage(Request $request) {
        try {
            $user = auth($this->guard)->user();

            $reference = $request->get('reference', null);

            $in = [
                ImageReport::REFMODEL_USER,
                ImageReport::REFMODEL_PHOTO,
            ];

            if( !\in_array($reference, $in) ) {
                return sendResponse();
            }

            $model_id = $request->get('model_id', null);

            if(!$model_id) {
                return sendResponse();
            }

            $foundReport = ImageReport::where('user_id', $user->id)
                        ->where('model_id', $model_id)
                        ->where('reference', $reference)
                        ->first();

            if($foundReport) {
                return sendResponse();
            }
            
            DB::beginTransaction();

            $comment = "";

            $report = ImageReport::create([
                'user_id' => $user->id,
                'model_id' => $model_id,
                'reference' => $reference,
            ]);

            if((!$report) || empty($report->id)) {
                DB::rollback();
                return sendResponse();
            }

            switch ($reference) {
                case ImageReport::REFMODEL_USER:
                    $appUser = AppUser::findOrFail($model_id);
                    $appUser->updateMetaValue('avatar_reported', json_encode(array(
                        'image' => $appUser->avatar,
                        'datetime' => strNowTime(),
                        'image_report_id' => $report->id,
                    )));
                    $comment = $request->get('comment_reported', __('User picture profile reported as inappropiate'));
                    break;
                case ImageReport::REFMODEL_PHOTO:
                    $photo = Photo::findOrFail($model_id);
                    $photo->status = Photo::STATUS_REPORTED;
                    $photo->times_report = intval($photo->times_report) + 1;
                    $photo->save();
                    $comment = $request->get('comment_reported', __('Travel picture reported as inappropiate'));
                    break;
            }

            $report->comment = $comment;
            $report->save();
            
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
}
