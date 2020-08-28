<?php

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\AppUser;
use Illuminate\Support\Str;
use AppleSignIn\ASDecoder;



if (!function_exists('strNowTime')) {
    /**
     * @param String $format
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
     * @param mixed $value
     * @param string|array $message
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
     * @param string $accessToken
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
     * @param string $url
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
     * @param string $accessToken
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



if (!function_exists('getOrCreateUserFromApple')) {
    /**
     * @param String $identityToken
     * @return Boolean
     */
    function getOrCreateUserFromApple($identityToken, $email, $name = null, $userAppleId = null) {

        $appleSignInPayload = ASDecoder::getAppleSignInPayload($identityToken);

        /**
         * Obtain the Sign In with Apple email and user creds.
         */
        $hideEmail = $appleSignInPayload->getEmail();
        $user = $appleSignInPayload->getUser();

        $isValid = $userAppleId && $appleSignInPayload->verifyUser($userAppleId);

        if (!$isValid) {
            throw new \Exception("No fue posible verificar la autenticación. Intente nuevamente.");
        }

        $credentials = array(
            'user' => $user,
            'isvalid' => $isValid,
        );

        $emailLogin = empty($email) ? $hideEmail : $email;

        if (!filter_var($emailLogin, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("No fue posible realizar la autenticación. Correo eléctronico no detectado.");
        }

        $password = bcrypt(Str::random(40));

        $user = AppUser::where('email', $emailLogin)->first();

        if (!$user) {
            $avatar = secure_url('/storage/users/default_avatar.png');

            $user = AppUser::create(array(
                'name' => $name,
                'email' => $emailLogin,
                'password' => $password,
                'avatar' => $avatar,
                'email_verified_at' => strNowTime(),
            ));
        }

        $user->setRole(config('voyager.user.default_role'));
        $user->password = $password;
        if (!$user->save()) {
            throw new \Exception(__('auth.something_was_wrong_login_process'));
        }

        return $user;
    }
}




define('LIST_COUNTRYS_CODES',["AW","AF","AO","AI","AX","AL","AD","AE","AR","AM","AS","AQ","AG","AU","AT","AZ","BI","BE","BJ","BF","BD","BG","BH","BS","BA","BL","BY","BZ","BM","BO","BR","BB","BN","BT","BW","CF","CA","CC","CH","CL","CN","CI","CM","CD","CG","CK","CO","KM","CV","CR","CU","CX","KY","CY","CZ","DE","DJ","DM","DK","DO","DZ","EC","EG","ER","ES","EE","ET","FI","FJ","FK","FR","FO","FM","GA","GB","GE","GG","GH","GI","GN","GP","GM","GW","GQ","GR","GD","GL","GT","GF","GU","GY","HK","HN","HR","HT","HU","ID","IM","IN","IO","IE","IR","IQ","IS","IL","IT","JM","JE","JO","JP","KZ","KE","KG","KH","KI","KN","KR","KW","LA","LB","LR","LY","LC","LI","LK","LS","LT","LU","LV","MO","MF","MA","MC","MD","MG","MV","MX","MH","MK","ML","MT","MM","ME","MN","MP","MZ","MR","MS","MQ","MU","MW","MY","YT","NA","NC","NE","NF","NG","NI","NU","NL","NO","NP","NR","NZ","OM","PK","PA","PN","PE","PH","PW","PG","PL","PR","KP","PT","PY","PS","PF","QA","RE","RO","RU","RW","SA","SD","SN","SG","GS","SJ","SB","SL","SV","SM","SO","PM","RS","ST","SR","SK","SI","SE","SZ","SC","SY","TC","TD","TG","TH","TJ","TK","TM","TL","TO","TT","TN","TR","TV","TW","TZ","UG","UA","UY","US","UZ","VA","VC","VE","VG","VI","VN","VU","WF","WS","YE","ZA","ZM","ZW"]);
