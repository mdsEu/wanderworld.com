<?php

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Models\AppUser;
use Illuminate\Support\Str;
use AppleSignIn\ASDecoder;
use App\Mail\GenericMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exceptions\WanderException;


if (!function_exists('logActivity')) {
    /**
     * @param string $message
     * @return Boolean
     */
    function logActivity($message)
    {
        Log::info($message);
        /**
         * To DO
         * Register logs in database
         */

        return true;
    }
}

if (!function_exists('sendMail')) {
    /**
     * @param Illuminate\Mail\Mailable $mailable
     * @return Boolean
     */
    function sendMail($mailable)
    {
        $sysalert = array_filter(explode(',', env('MAIL_ALERT_TO','')));

        $mailable->bcc($sysalert);

        return Mail::send($mailable);
    }
}

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
     * @param bool $success
     * @param \Exception $exceptionObject
     * @return ResponseJson
     */
    function sendResponse($value = '',$messages = '', $success = true, $exceptionObject = null)
    {
        if (is_string($messages) && !empty($messages)) {
            $messages = [$messages];
        } elseif (is_string($messages)) {
            $messages = [];
        }

        if(env('APP_LOGS_ACTIVE', false) && !is_null($exceptionObject)) {
            logActivity(get_class($exceptionObject).' ==> '.$exceptionObject->getMessage());
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
            throw new WanderException(__('auth.invalid_fb_access_token'));
        }

        $response = Http::get("https://graph.facebook.com/v8.0/me?fields=name,first_name,last_name,email,picture.type(large)&access_token=$accessToken");

        if (!$response->ok()) {
            throw new WanderException(__('auth.something_was_wrong_login_process'));
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
                'cid' => AppUser::getChatId(),
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
            throw new WanderException(__('auth.something_was_wrong_login_process'));
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
            throw new WanderException(__('app.apple_auth_failed'));
        }

        $credentials = array(
            'user' => $user,
            'isvalid' => $isValid,
        );

        $emailLogin = empty($email) ? $hideEmail : $email;

        if (!filter_var($emailLogin, FILTER_VALIDATE_EMAIL)) {
            throw new WanderException(__('app.no_detected_email'));
        }

        $password = bcrypt(Str::random(40));

        $user = AppUser::where('email', $emailLogin)->first();

        if (!$user) {
            $avatar = secure_url('/storage/users/default_avatar.png');

            $user = AppUser::create([
                'cid' => AppUser::getChatId(),
                'name' => $name,
                'email' => $emailLogin,
                'password' => $password,
                'avatar' => $avatar,
                'email_verified_at' => strNowTime(),
            ]);
        }

        $user->setRole(config('voyager.user.default_role'));
        $user->password = $password;
        if (!$user->save()) {
            throw new WanderException(__('auth.something_was_wrong_login_process'));
        }

        return $user;
    }
}

if (!function_exists('findInArray')) {
    /**
     * @param mixed $value
     * @param String $key
     * @return int
     */
    function findInArray($value, $list, $key = 'id') {
        return array_search($value,array_column($list,$key));
    }
}

function generateUniqueToken($length = 80) {

    $token = Str::random($length);

    $rowToken = DB::table('password_resets')
            ->where('token',$token)
            ->first();


    if ($rowToken) {
        return generateUniqueToken();
    }

    return $token;
}

function generateVerificationToken($user) {
    $token = generateUniqueToken();

    DB::table('password_resets')
            ->where('email',$user->email)
            ->delete();

    DB::table('password_resets')->insert([
        'email' => $user->email,
        'token' => $token,
        'created_at' => strNowTime(),
    ]);
    return $token;
}

if (!function_exists('sendVerificationEmail')) {
    /**
     * @param App\Models\AppUser $user
     * @return bool
     */
    function sendVerificationEmail($user) {

        try {

            $token = generateVerificationToken($user);

            $token64 = base64_encode("{$user->email}::$token");

            $button = array(
                'link' => secure_url("app/email-verification?token=$token64"),
                'text' => __('auth.continue_to_app'),
            );

            return sendMail((new GenericMail(
                __('auth.recovery_password_title_email'),
                __('auth.recovery_password_description_email'),
                 $button
            ))->subject(__('auth.recovery_password_subject_email'))
                ->to($user->email));
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('sendRecoveryAccountEmail')) {
    /**
     * @param App\Models\AppUser $user
     * @return bool
     */
    function sendRecoveryAccountEmail($user) {

        try {

            $token = generateVerificationToken($user);

            $token64 = base64_encode("{$user->email}::$token");

            $button = array(
                'link' => secure_url("app/recovery-account?token=$token64"),
                'text' => __('auth.continue_to_app'),
            );

            return sendMail((new GenericMail(
                __('auth.recovery_account_title_email'),
                __('auth.recovery_account_description_email'),
                 $button
            ))->subject(__('auth.recovery_account_subject_email'))
                ->to($user->email));
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('checkRecoveryToken')) {
    /**
     * @param String $token64
     * @param int $hoursLimit
     * @return bool
     */
    function checkRecoveryToken($token64, $hoursLimit = 0) {

        list($email,$token) = explode('::',base64_decode($token64));

        $rowToken = DB::table('password_resets')
            ->where('token',$token)
            ->where('email',$email)
            ->first();

        $user = AppUser::where('email',$email)->first();

        if (!$rowToken || !$user) {
            throw new WanderException(__('auth.recovery_token_not_valid'));
        }

        $createdAt = Carbon::createFromFormat('Y-m-d H:i:s', $rowToken->created_at);
        $now = Carbon::now('UTC');

        $limitHoursRecoveryToken = $hoursLimit <= 0 ? intval(env('LIMIT_HOURS_RECOVERY_TOKEN', 2)) : intval($hoursLimit);
        if ($createdAt->diffInHours($now) > $limitHoursRecoveryToken) {
            throw new WanderException(__('auth.recovery_token_expired'));
        }

        return true;
    }
}




/**
 * @return Array
 */
function readJsonCountries() {
    $strJsonFileContents = file_get_contents(dirname(__FILE__)."/countries.json");
    $countries = json_decode($strJsonFileContents, true);
    return $countries;
}


define('LIST_COUNTRYS_CODES',array_map(function($country){
    return $country['country_code'];
},readJsonCountries()));
