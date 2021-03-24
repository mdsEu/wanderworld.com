<?php

namespace App\Http\Controllers;

use App\Exceptions\WanderException;
use App\Exceptions\ChatException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\AppUser;

use JWTAuth;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct() {
        //$this->middleware('auth:api', ['except' => ['login']]);
        $this->guard = 'api';
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login() {

        try {
            $credentials = request(['email', 'password']);

            $user = AppUser::where('email',$credentials['email'])->firstOrFail();

            if ($user->status == AppUser::STATUS_PENDING) {
                return sendResponse(null,__('auth.email_not_verified'), false);
            }

            if ($user->status == AppUser::STATUS_INACTIVE) {
                return sendResponse(null,__('auth.inactive_user'), false);
            }

            $token = auth($this->guard)->attempt($credentials);

            if (!$token) {
                return sendResponse(null,__('auth.credentials_not_valid'), false);
            }

            return $this->respondWithToken($token);
        } catch (QueryException $qe) {
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null, __('auth.credentials_not_valid'), false, $notFoundE);
        } catch (WanderException $we) {
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            return sendResponse(null, $e->getMessage(), false, $e);
        }
    }

    /**
     * Get a JWT via given credentials.
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function facebookLogin(Request $request) {

        $accessToken = $request->get('accessToken', null);

        try {
            if (!fbValidAccessToken($accessToken)) {
                return sendResponse(null,__('auth.facebook_access_failed'), false);
            }


            $params = $request->only([
                'city',
            ]);

            $validator = Validator::make($params, [
                'city.name' => 'required',
                'city.place_id' => 'required',
                'city.country.name' => 'required',
                'city.country.code' => [
                    'required',
                    Rule::in(LIST_COUNTRYS_CODES),
                ],
            ]);

            if ($validator->fails()) {
                return sendResponse(null,$validator->messages(),false);
            }

            $countries = readJsonCountries();

            $idxFoundCountry = findInArray($params['city']['country']['code'], $countries, 'country_code');

            if ($idxFoundCountry === false) {
                //Never will happen. Previously validated....
            }
            $foundCountry = $countries[$idxFoundCountry];


            $newAppUser = array(
                'continent_code' => $foundCountry['continent_code'],
                'country_code' => $foundCountry['country_code'],
                'city_gplace_id' => $params['city']['place_id'],
            );

            $user = getOrCreateUserFromFacebook($accessToken, $newAppUser);

            if ($user->status == AppUser::STATUS_PENDING) {
                return sendResponse(null,__('auth.email_not_verified'), false);
            }

            if ($user->status == AppUser::STATUS_INACTIVE) {
                return sendResponse(null,__('auth.inactive_user'), false);
            }

            $token = auth($this->guard)->login($user);

            if (!$token) {
                return sendResponse(null,__('auth.credentials_not_valid'), false);
            }

            return $this->respondWithToken($token);
        } catch (\Exception $e) {
            return sendResponse(null,$e->getMessage(), false);
        }
    }

    /**
     * Get a JWT via given credentials.
     * @param \Illuminate\Http\Request $request
     * @param String $identityToken
     * @return \Illuminate\Http\JsonResponse
     */
    public function appleLogin(Request $request, $identityToken) {

        try {

            $params = $request->only([
                'name',
                'email',
                'city',
            ]);

            $validator = Validator::make($params, [
                'name' => 'required|max:40',
                'city.name' => 'required',
                'city.place_id' => 'required',
                'city.country.name' => 'required',
                'city.country.code' => [
                    'required',
                    Rule::in(LIST_COUNTRYS_CODES),
                ],
            ]);

            if ($validator->fails()) {
                return sendResponse(null,$validator->messages(),false);
            }

            $countries = readJsonCountries();

            $idxFoundCountry = findInArray($params['city']['country']['code'], $countries, 'country_code');

            if ($idxFoundCountry === false) {
                //Never will happen. Previously validated....
            }
            $foundCountry = $countries[$idxFoundCountry];

            $appleUserId = $request->get('apple_user_id', null);


            $newAppUser = array(
                'name' => trim($params['name']),
                'email' => $params['email'],
                'continent_code' => $foundCountry['continent_code'],
                'country_code' => $foundCountry['country_code'],
                'city_gplace_id' => $params['city']['place_id'],
            );

            $user = getOrCreateUserFromApple($identityToken, $appleUserId, $newAppUser);

            if ($user->status == AppUser::STATUS_PENDING) {
                return sendResponse(null,__('auth.email_not_verified'), false);
            }

            if ($user->status == AppUser::STATUS_INACTIVE) {
                return sendResponse(null,__('auth.inactive_user'), false);
            }

            $token = auth($this->guard)->login($user);
            if (!$token) {
                return sendResponse(null,__('auth.credentials_not_valid'), false);
            }

            return sendResponse($token);
        } catch (\Exception $e) {
            return sendResponse(null,$e->getMessage(),false);
        }
    }


    /**
     * Start registration process.
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function registration(Request $request) {
        try {
            $params = $request->only([
                'fullname',
                'nickname',
                'email',
                'password',
                'birthday',
                'city',
                'cellphone',
                'terms',
            ]);

            $validator = Validator::make($params, [
                'fullname' => 'required|max:40',
                'nickname' => 'max:40',
                'email' => 'required|email|max:75|unique:app_users,email',
                'password' => [
                    'required',
                    'max:20',
                    function($attribute, $value, $fail) {
                        $this->passwordRules($attribute, $value, $fail);
                    }
                ],
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
                'city.name' => 'required',
                'city.place_id' => 'required',
                'city.country.name' => 'required',
                'city.country.code' => [
                    'required',
                    Rule::in(LIST_COUNTRYS_CODES),
                ],
                'cellphone.dial' => 'required',
                'cellphone.number' => 'required',
                'terms' => 'accepted',
            ]);

            if ($validator->fails()) {
                return sendResponse(null,$validator->messages(),false);
            }

            $countries = readJsonCountries();

            $idxFoundCountry = findInArray($params['city']['country']['code'], $countries, 'country_code');

            if ($idxFoundCountry === false) {
                //Never will happen. Previously validated....
            }
            $foundCountry = $countries[$idxFoundCountry];

            $defaultAvatar = cloneAvatar(public_path('images/default_avatar.png'));//AppUser::DEFAULT_AVATAR;

            DB::beginTransaction();

            $newAppUser = array(
                'cid' => AppUser::getChatId(),
                'name' => $params['fullname'],
                'email' => $params['email'],
                'nickname' => isset($params['nickname']) ? $params['nickname'] : null,
                'avatar' => $defaultAvatar,
                'continent_code' => $foundCountry['continent_code'],
                'country_code' => $foundCountry['country_code'],
                'city_gplace_id' => $params['city']['place_id'],
                'password' => bcrypt($params['password']),
            );

            $user = AppUser::create($newAppUser);

            if(!($user && $user->id)) {
                throw new WanderException(__("auth.user_not_created_try_again"));
            }

            $userReturn = $user->toArray();

            $user->updateMetaValue('birthday', $params['birthday']);
            $user->updateMetaValue('is_default_avatar', 'yes');
            
            $phone = sanitizePhone($params['cellphone']['dial'].$params['cellphone']['number']);
            $user->updateMetaValue('phone', $phone);
            $user->updateMetaValue('phone_dial', $params['cellphone']['dial']);
            $user->updateMetaValue('phone_number', $params['cellphone']['number']);

            sendVerificationEmail($user);

            DB::commit();

            return sendResponse($userReturn);
        } catch (QueryException $qe) {
            DB::rollback();
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            DB::rollback();
            return sendResponse(null, __('app.data_not_found'), false, $notFoundE);
        } catch (WanderException $we) {
            DB::rollback();
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (ChatException $ce) {
            DB::rollback();
            return sendResponse(null, __('app.no_registration_try_again_please'), false, $ce);
        } catch (\Exception $e) {
            DB::rollback();
            return sendResponse(null, $e->getMessage(), false, $e);
        }
    }

    /**
     * Start recovery token verification process.
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyEmail(Request $request) {
        try {
            $token64 = $request->get('token64');

            checkRecoveryToken($token64);

            list($email,$token) = explode('::',base64_decode($token64));

            $user = AppUser::where('email',$email)->first();

            $user->email_verified_at = strNowTime();
            $user->status = AppUser::STATUS_ACTIVE;

            if (!$user->save()) {
                throw new WanderException(__('auth.something_wrong_updating_user_info'));
            }

            return sendResponse($user);
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

    /**
     * Update password.
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePassword(Request $request) {
        try {
            $params = $request->only([
                'current_password',
                'password',
                'confirm_password',
            ]);

            $validator = Validator::make(['password' => $params['password']], [
                'password' => [
                    'required',
                    'max:20',
                    function($attribute, $value, $fail) {
                        $this->passwordRules($attribute, $value, $fail);
                    }
                ],
            ]);

            if ($validator->fails()) {
                return sendResponse(null,$validator->messages(),false);
            }

            $user = auth($this->guard)->user();
            
            
            if(!auth($this->guard)->validate(array(
                'email' => $user->email,
                'password' => $params['current_password'],
            ))) {
                throw new WanderException(__('app.current_password_incorrect'));
            }

            if($params['password'] !== $params['confirm_password']) {
                throw new WanderException(__('app.passwords_no_match'));
            }

            $user->password = bcrypt($params['password']);

            if (!$user->save()) {
                throw new WanderException(__('auth.something_wrong_updating_user_info'));
            }

            return sendResponse();
        } catch (QueryException $qe) {
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null, __('app.user_not_found'), false, $notFoundE);
        } catch (WanderException $we) {
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            return sendResponse(null, $e->getMessage(), false, $e);
        }
    }

    /**
     * Reset password.
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request) {
        try {

            $password = $request->get('password');

            $validator = Validator::make(['password' => $password], [
                'password' => [
                    'required',
                    'max:20',
                    function($attribute, $value, $fail) {
                        $this->passwordRules($attribute, $value, $fail);
                    }
                ],
            ]);

            if ($validator->fails()) {
                return sendResponse(null,$validator->messages(),false);
            }

            $token64 = $request->get('token64');

            checkRecoveryToken($token64);

            list($email,$token) = explode('::',base64_decode($token64));

            $user = AppUser::where('email',$email)->firstOrFail();

            $user->password = bcrypt($password);

            if (!$user->save()) {
                throw new WanderException(__('auth.something_wrong_updating_user_info'));
            }

            return sendResponse($user);
        } catch (QueryException $qe) {
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null, __('app.user_not_found'), false, $notFoundE);
        } catch (WanderException $we) {
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            return sendResponse(null, $e->getMessage(), false, $e);
        }
    }


    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me() {
        try {
            $user = auth($this->guard)->user();
            if (!$user) {
                return sendResponse(null,__('auth.user_not_found'), false);
            }
            return sendResponse($user);
        } catch (Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return sendResponse(null,__('auth.token_expired'), false, $e);
        } catch (Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return sendResponse(null,__('auth.token_invalid'), false, $e);
        } catch (Tymon\JWTAuth\Exceptions\JWTException $e) {
            return sendResponse(null,__('auth.token_absent'), false, $e);
        } catch (QueryException $qe) {
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null, __('app.user_not_found'), false, $notFoundE);
        } catch (ChatException $ce) {
            return sendResponse(null, __('app.try_again_please'), false, $ce);
        } catch (WanderException $we) {
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            return sendResponse(null, $e->getMessage(), false, $e);
        }
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout() {
        try {
            auth($this->guard)->logout(true);
            return sendResponse();
        } catch (Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return sendResponse(null,__('auth.token_expired'), false, $e);
        } catch (Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return sendResponse(null,__('auth.token_invalid'), false, $e);
        } catch (Tymon\JWTAuth\Exceptions\JWTException $e) {
            return sendResponse(null,__('auth.token_absent'), false, $e);
        } catch (QueryException $qe) {
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null, __('app.user_not_found'), false, $notFoundE);
        } catch (WanderException $we) {
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            return sendResponse(null, $e->getMessage(), false, $e);
        }
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh() {
        return $this->respondWithToken(auth($this->guard)->refresh(true));
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        /*return sendResponse([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth($this->guard)->factory()->getTTL() * 60
        ]);*/
        return sendResponse($token);
    }


    /**
     * Update password.
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function sendEmailRecoveryAccount(Request $request) {
        try {
            $email = $request->get('email', null);

            $user = AppUser::where('email',$email)->firstOrFail();

            if(!($user && $user->id)) {
                throw new WanderException(__('auth.success_email_sent_recovery_account'));
            }

            sendRecoveryAccountEmail($user);

            return sendResponse(__('auth.success_email_sent_recovery_account'));
        } catch (QueryException $qe) {
            return sendResponse(null, __('app.success_email_sent_recovery_account'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null, __('app.success_email_sent_recovery_account'), false, $notFoundE);
        } catch (WanderException $we) {
            return sendResponse(null, __('app.success_email_sent_recovery_account'), false, $we);
        } catch (\Exception $e) {
            return sendResponse(null, __('app.success_email_sent_recovery_account'), false, $e);
        }
    }


    public function passwordRules($attribute, $value, $fail) {
        $minCharsPassword = env('MIN_CHARS_PASSWORD', 8);
        $validLength = strlen(trim($value)) >= $minCharsPassword;
        if (!$validLength) {
            $fail(__('validation.eight_field_rule',array(
                'nchars' => $minCharsPassword,
            )));
            return;
        }

        $validUpper = preg_match('/[A-Z]/',$value);
        if (!$validUpper) {
            $fail(__('validation.any_uppercase_letter_rule'));
            return;
        }

        $validLower = preg_match('/[a-z]/',$value);
        if (!$validLower) {
            $fail(__('validation.any_lowercase_letter_rule'));
            return;
        }

        $validNumber = preg_match('/[0-9]/',$value);
        if (!$validNumber) {
            $fail(__('validation.any_number_rule'));
            return;
        }
    }

}
