<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
        $credentials = request(['email', 'password']);

        $token = auth($this->guard)->attempt($credentials);

        if (!$token) {
            return sendResponse(null,__('auth.credentials_not_valid'), false);
        }

        return $this->respondWithToken($token);
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

            $token = auth($this->guard)->login($user);
            if (!$token) {
                throw new \Exception("No fue posible realizar la autenticación. Intente nuevamente.");
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

            $validator = Validator::make($request->all(), [
                'full_name' => 'required|max:20',
                'nickname' => 'required|max:20',//unique
                'email' => 'required|email',//unique
                'password' => 'required',
                'birthday' => 'required',
                'city.name' => 'required',
                'city.place_id' => 'required',
                'city.country.name' => 'required',
                'city.country.code' => [
                    'required',
                    Rule::in(LIST_COUNTRYS_CODES),
                ],
                'cellphone.dial' => 'required',
                'cellphone.number' => 'required',
            ]);

            if ($validator->fails()) {
                return sendResponse(null,$validator->messages(),false);
            }

            return sendResponse($request->all());
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
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return sendResponse(null,__('auth.user_not_found'), false);
            }
        } catch (Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return sendResponse(null,__('auth.token_expired'), false);
        } catch (Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return sendResponse(null,__('auth.token_invalid'), false);
        } catch (Tymon\JWTAuth\Exceptions\JWTException $e) {
            return sendResponse(null,__('auth.token_absent'), false);
        }
        //return response()->json(auth($this->guard)->user());
        return response()->json($user);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth($this->guard)->logout(true);

        return response()->json(['message' => 'Successfully logged out']);
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
}
