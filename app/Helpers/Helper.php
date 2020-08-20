<?php

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\AppUser;
use Illuminate\Support\Str;


if (!function_exists('strNowTime')) {
    /**
     * @param $format String
     * @return String
     */
    function strNowTime($format = 'Y-m-d H:i:s')
    {
        return Carbon::now('UTC')->format($format);
    }
}

if (!function_exists('strToday')) {
    /**
     * @return String
     */
    function strToday()
    {
        return strNowTime('Y-m-d');
    }
}

if (!function_exists('sendJson')) {
    /**
     * @param $value mixed
     * @param $message string | array
     * @return ResponseJson
     */
    function sendResponse($value,$messages = '', $success = true)
    {
        if (is_string($messages) && !empty($messages)) {
            $messages = [$messages];
        } elseif (is_string($messages)) {
            $messages = [];
        }
        return response()->json(array(
                'success' => $success,
                'data' => $value,
                'messages' => $messages,
            )
        );
    }
}

if (!function_exists('fbValidAccessToken')) {
    /**
     * @param $accessToken string
     * @return Boolean
     */
    function fbValidAccessToken($accessToken)
    {
        if (empty($accessToken)) {
            return false;
        }
        $response = Http::get("https://graph.facebook.com/v8.0/me?fields=email&access_token=$accessToken");
        return $response->ok() && !empty($response->json()['email']);
    }
}

if (!function_exists('checkFileExists')) {
    /**
     * @param $url string
     * @return Boolean
     */
    function checkFileExists($url)
    {
        if (empty($url)) {
            return false;
        }
        $response = Http::get($url);
        return $response->ok();
    }
}

if (!function_exists('getOrCreateUserFromFacebook')) {
    /**
     * @param $accessToken string
     * @return Boolean
     */
    function getOrCreateUserFromFacebook($accessToken)
    {
        if (!fbValidAccessToken($accessToken)) {
            throw new \Exception(__('auth.invalid_fb_access_token'));
        }

        $response = Http::get("https://graph.facebook.com/v8.0/me?fields=name,first_name,last_name,email,picture.type(large)&access_token=$accessToken");

        if (!$response->ok()) {
            throw new \Exception(__('auth.something_was_wrong_login_process'));
        }

        $userFBInfo = $response->json();

        $user = AppUser::where('email',$userFBInfo['email'])->first();

        $password = bcrypt(Str::random(40));

        $avatar = secure_url('/storage/users/default_avatar.png');

        if (
            $userFBInfo['picture'] &&
            $userFBInfo['picture']['data'] &&
            $userFBInfo['picture']['data']['url'] &&
            checkFileExists($userFBInfo['picture']['data']['url'])
            ) {
            $avatar = $userFBInfo['picture']['data']['url'];
        }

        if (!$user) {
            $user = AppUser::create([
                'name' => $userFBInfo['name'],
                'email' => $userFBInfo['email'],
                'password' => $password,
                'avatar' => $avatar,
                'email_verified_at' => strNowTime(),
            ]);
            /**
             * To DO: Create Inbox Chat User in Chat Backend
             */
        }

        $user->password = $password;

        if (!$user->save()) {
            throw new \Exception(__('auth.something_was_wrong_login_process'));
        }

        return $user;
    }
}
