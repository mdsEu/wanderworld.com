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
use Illuminate\Support\Collection;
use App\Exceptions\WanderException;


if (!function_exists('getStrFakeVal')) {
    /**
     * @param mixed $val
     * @return String
     */
    function getStrFakeVal($val) {
        return empty($val) ? Str::random(80) : $val;
    }
}

if (!function_exists('logActivity')) {
    /**
     * @param mixed $message
     * @return Boolean
     */
    function logActivity($message)
    {
        if(!is_string($message)) {
            $message = var_export($message, true);
        }
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
        try {
            $sysalert = array_filter(explode(',', env('MAIL_ALERT_TO','')));

            $mailable->bcc($sysalert);

            Mail::send($mailable);

            return true;
        } catch (\Exception $e) {
            return false;
        }
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
                /*
                'nickname' => $params['nickname'],
                'continent_code' => $foundCountry['continent_code'],
                'country_code' => $foundCountry['country_code'],
                'city_gplace_id' => $params['city']['place_id'],
                */
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

        $user->updateMetaValue('facebook_user_id',$userFBInfo['id']);

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
                /*
                'nickname' => $params['nickname'],
                'continent_code' => $foundCountry['continent_code'],
                'country_code' => $foundCountry['country_code'],
                'city_gplace_id' => $params['city']['place_id'],
                */
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
     * @param \App\Models\AppUser $user
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
     * @param \App\Models\AppUser $user
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

if (!function_exists('arrayFind')) {
    /**
     * @param array $list
     * @param callable $centinela
     * @return mixed
     */
    function arrayFind(array $list,callable $centinela) {
        foreach($list as $item) {
            if( call_user_func($centinela, $item) ) {
                return $item;
            }
        }
        return null;
    }
}


if (!function_exists('getGeoPlaceName')) {
    /**
     * @param String $address_components
     * @param String $key
     * @return array
     */
    function getGeoPlaceName($address_components, $key) {

        $addressCompPlace = null;
        switch ($key) {
            case 'city':
                
                $addressCompPlace = arrayFind($address_components,function($adrComp) {
                    return !(array_search('locality', $adrComp['types']) === false);
                });
                if ($addressCompPlace) {
                    return $addressCompPlace;
                }
                
                $addressCompPlace = arrayFind($address_components,function($adrComp) {
                    return !(array_search('administrative_area_level_2', $adrComp['types']) === false);
                });
                if ($addressCompPlace) {
                    return $addressCompPlace;
                }
                
                $addressCompPlace = arrayFind($address_components,function($adrComp) {
                    return !(array_search('administrative_area_level_1', $adrComp['types']) === false);
                });
                if (!$addressCompPlace) {
                    return null;
                }
                return $addressCompPlace;
            case 'country':
                $addressCompPlace = arrayFind($address_components,function($adrComp) {
                    return !(array_search('country', $adrComp['types']) === false);
                });
                if (!$addressCompPlace) {
                    return null;
                }
                return $addressCompPlace;

            default:
                return null;
        }
    }
}


if (!function_exists('areFriends')) {
    /**
     * @param \App\Models\AppUser $user1
     * @param \App\Models\AppUser $user2
     * @return bool
     */
    function areFriends(AppUser $user1, AppUser $user2) {
        if($user1->id === $user2->id) {
            return false;
        }
        $userFound = $user1->activeFriends()->find($user2->id);
        return ((bool)$userFound);
    }
}

if (!function_exists('isJsonString')) {
    /**
     * @param String $strJson
     * @return bool
     */
    function isJsonString($strJson) {
        if( empty($strJson) || !is_string($strJson) ) {
            return false;
        }
        try {
            parseJsonToArray( $strJson );
            return true;
        } catch(\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('parseJsonToArray')) {
    /**
     * @param String $strJson
     * @return bool
     */
    function parseJsonToArray($strJson, $defaultVal = []) {
        $arr = \json_decode($strJson, true);
        switch( \json_last_error() ) {
            case JSON_ERROR_NONE:
                return $arr;
            break;
            case JSON_ERROR_DEPTH:
            case JSON_ERROR_STATE_MISMATCH:
            case JSON_ERROR_CTRL_CHAR:
            case JSON_ERROR_SYNTAX:
            case JSON_ERROR_UTF8:
            default:
                return $defaultVal;
        }
    }
}

if (!function_exists('parseStrToJson')) {
    /**
     * @param String $strJson
     * @return bool
     */
    function parseStrToJson($strJson, $defaultVal = null) {
        $result = \json_decode($strJson, false);
        switch( \json_last_error() ) {
            case JSON_ERROR_NONE:
                return $result;
            break;
            case JSON_ERROR_DEPTH:
            case JSON_ERROR_STATE_MISMATCH:
            case JSON_ERROR_CTRL_CHAR:
            case JSON_ERROR_SYNTAX:
            case JSON_ERROR_UTF8:
            default:
                return $defaultVal;
        }
    }
}

if (!function_exists('getAtrValue')) {
    /**
     * @param Object $element
     * @param String $key
     * @return mixed
     */
    function getAtrValue($element, $key, $defaultVal = null) {
        if(!is_object($element)) {
            return $defaultVal;
        }
        return ((!isset($element->$key)) || empty($element->$key)) ? $defaultVal : $element->$key;
    }
}

if (!function_exists('brokeFriendRelationship')) {
    /**
     * @param \App\Models\AppUser $user1
     * @param \App\Models\AppUser $user2
     * @return bool
     */
    function brokeFriendRelationship(AppUser $user1, AppUser $user2) {
        $user1->refreshInvitationsContactsForDeleting($user2);
        
        $user1->friends()->detach($user2->id);
        $user2->friends()->detach($user1->id);
        
        event(new \App\Events\FriendRelationshipDeleted($user1,$user2));

        return true;
    }
}


if (!function_exists('makeFriendRelationship')) {
    /**
     * @param \App\Models\AppUser $user1
     * @param \App\Models\AppUser $user2
     * @return bool
     */
    function makeFriendRelationship(AppUser $user1, AppUser $user2) {

        $user1->friends()->syncWithoutDetaching($user2, ['status' => AppUser::FRIEND_STATUS_ACTIVE]);
        $user2->friends()->syncWithoutDetaching($user1, ['status' => AppUser::FRIEND_STATUS_ACTIVE]);

        event(new \App\Events\FriendRelationshipCreated($user1,$user2));

        return true;
    }
}

if (!function_exists('sanitizePhone')) {
    /**
     * @param String $phone
     * @return String|null
     */
    function sanitizePhone($phone) {
        if(is_null($phone) || (!is_string($phone))) {
            return null;
        }
        $symbol = '+';
        if(strpos($phone, '+') === false) {
            $symbol = '';
        }
        return $symbol.preg_replace("/[^0-9]/i", "", $phone);
    }
}


if (!function_exists('getPaginate')) {
    /**
     * @param \Illuminate\Support\Collection $items
     * @param int $perPage
     * @param int|null $page
     * @return String|null
     */
    function getPaginate($items, $perPage, $page = null) {
        if(!$page || !is_numeric($page)) {
            $page = request()->get('page', 1);
            $page = is_numeric($page) ? intval($page) : 1;
        }

        $items = $items instanceof Collection ? $items : Collection::make($items);

        $startIdx = ($page * $perPage) - $perPage;

        $total = $items->count();

        $sliceItems = collect($items->slice($startIdx, $perPage)->values());

        $pagination = new \Illuminate\Pagination\LengthAwarePaginator($sliceItems, $total, $perPage, $page);
        return $pagination;
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
