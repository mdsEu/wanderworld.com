<?php

namespace App\Http\Controllers;

use App\Exceptions\WanderException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Models\AppUser;

use JWTAuth;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth:api', ['except' => ['login']]);
        $this->guard = 'api';
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {

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
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null,__('auth.credentials_not_valid'), false);
        } catch (\Exception $e) {
            return sendResponse(null,$e->getMessage(),false);
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

            $user = getOrCreateUserFromFacebook($accessToken);

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
            $email = $request->get('email', null);
            $userAppleId = $request->get('user_apple_id', null);
            $userName = $request->get('name', null);

            $user = getOrCreateUserFromApple($identityToken, $email, $userName, $userAppleId);

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
                'fullname' => 'required|max:20',
                'nickname' => 'required|max:20|unique:app_users,nickname',
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

            $defaultAvatar = secure_url('storage/users/default_avatar.png');

            $newAppUser = array(
                'cid' => AppUser::getChatId(),
                'name' => $params['full_name'],
                'email' => $params['email'],
                'nickname' => $params['nickname'],
                'avatar' => $defaultAvatar,
                'continent_code' => $foundCountry['continent_code'],
                'country_code' => $foundCountry['country_code'],
                'city_gplace_id' => $params['city']['place_id'],
                'password' => bcrypt($params['password']),
            );

            $user = AppUser::create($newAppUser);

            if(!($user && $user->id)) {
                throw new \Exception("auth.user_not_created_try_again");
            }

            sendVerificationEmail($user);

            return sendResponse($user);
        } catch (\Exception $e) {
            return sendResponse(null,$e->getMessage(),false);
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
                throw new \Exception(__('auth.something_wrong_updating_user_info'));
            }

            return sendResponse($user);
        } catch (\Exception $e) {
            return sendResponse(null,$e->getMessage(),false);
        }
    }

    /**
     * Update password.
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePassword(Request $request) {
        try {
            $password = $request->get('password');

            $user = AppUser::where('email',$email)->firstOrFail();
            $user->password = bcrypt($password);

            if (!$user->save()) {
                throw new \Exception(__('auth.something_wrong_updating_user_info'));
            }

            return sendResponse($user);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null,__('auth.user_not_found'),false);
        } catch (\Exception $e) {
            return sendResponse(null,$e->getMessage(),false);
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
                throw new \Exception(__('auth.something_wrong_updating_user_info'));
            }

            return sendResponse($user);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null,__('auth.user_not_found'),false);
        } catch (\Exception $e) {
            return sendResponse(null,$e->getMessage(),false);
        }
    }


    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        try {
            $user = auth($this->guard)->user();
            if (!$user) {
                return sendResponse(request()->get('token'),__('auth.user_not_found'), false);
            }
        } catch (Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return sendResponse(null,__('auth.token_expired'), false);
        } catch (Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return sendResponse(null,__('auth.token_invalid'), false);
        } catch (Tymon\JWTAuth\Exceptions\JWTException $e) {
            return sendResponse(null,__('auth.token_absent'), false);
        }
        return sendResponse($user);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth($this->guard)->logout(true);

        return sendResponse();
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
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
    protected function sendEmailRecoveryAccount(Request $request)
    {
        try {
            $email = $request->get('email', null);

            $user = AppUser::where('email',$email)->firstOrFail();

            if(!($user && $user->id)) {
                throw new \Exception(__('auth.success_email_sent_recovery_account'));
            }

            sendRecoveryAccountEmail($user);

            return sendResponse(__('auth.success_email_sent_recovery_account'));
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(__('auth.success_email_sent_recovery_account'));
        } catch (\Exception $e) {
            return sendResponse(__('auth.success_email_sent_recovery_account'));
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
