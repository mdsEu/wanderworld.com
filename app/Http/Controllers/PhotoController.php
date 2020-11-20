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
}
