<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
