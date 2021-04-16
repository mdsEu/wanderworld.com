<?php

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Models\AppUser;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
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

if(!function_exists('cloneAvatar')) {
    /**
     * @param string $path
     * @return String
     */
    function cloneAvatar($path, $fromUrl = false) {
        if($fromUrl) {
            $nameFile = getRandomName($path);
            if(!$nameFile) {
                return cloneAvatar(public_path('images/default_avatar.png'));
            }
            return Storage::disk(config('voyager.storage.disk'))->put('avatars/'.$nameFile, file_get_contents($path), 'public');
        }
        return Storage::disk(config('voyager.storage.disk'))->putFile('avatars', new \Illuminate\Http\File($path), 'public');
    }
}

if (!function_exists('getOrCreateUserFromFacebook')) {
    /**
     * @param string $accessToken
     * @return Boolean
     */
    function getOrCreateUserFromFacebook($accessToken, $paramsUser)
    {
        if (!fbValidAccessToken($accessToken)) {
            throw new WanderException(__('auth.invalid_fb_access_token'));
        }

        $response = Http::get("https://graph.facebook.com/v8.0/me?fields=name,first_name,last_name,email,picture.type(large)&access_token=$accessToken");

        if (!$response->ok()) {
            throw new WanderException(__('auth.something_was_wrong_login_process'));
        }

        $userFBInfo = $response->json();

        \logActivity($userFBInfo);

        if(empty($userFBInfo['email'])) {
            throw new WanderException(__('No correo asociado'));
        }

        $user = AppUser::where('email',$userFBInfo['email'])->first();

        $password = bcrypt(Str::random(40));

        $defaultAvatar = cloneAvatar(public_path('images/default_avatar.png'));//AppUser::DEFAULT_AVATAR;

        if (
            $userFBInfo['picture'] &&
            $userFBInfo['picture']['data'] &&
            $userFBInfo['picture']['data']['url'] &&
            checkFileExists($userFBInfo['picture']['data']['url'])
            ) {
            $defaultAvatar = cloneAvatar($userFBInfo['picture']['data']['url'], true);
        }

        if (!$user) {

            $attrsCreate = [
                'cid' => AppUser::getChatId(),
                'name' => $userFBInfo['name'],
                'email' => $userFBInfo['email'],
                'password' => $password,
                'avatar' => $defaultAvatar,
                'status' => AppUser::STATUS_ACTIVE,
                'email_verified_at' => strNowTime(),
            ];

            $user = AppUser::create(array_merge(
                $paramsUser,
                $attrsCreate
            ));
        }

        $user->password = $password;

        $user->updateMetaValue('facebook_user_id',$userFBInfo['id']);

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
    function getOrCreateUserFromApple($identityToken, $userAppleId, $paramsUser) {

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

        $emailLogin = empty($paramsUser['email']) ? $hideEmail : $paramsUser['email'];

        if (!filter_var($emailLogin, FILTER_VALIDATE_EMAIL)) {
            throw new WanderException(__('app.no_detected_email'));
        }

        $password = bcrypt(Str::random(40));

        $user = AppUser::where('email', $emailLogin)->first();

        if (!$user) {
            $defaultAvatar = cloneAvatar(public_path('images/default_avatar.png'));//AppUser::DEFAULT_AVATAR;

            $attrsCreate = [
                'cid' => AppUser::getChatId(),
                'email' => $emailLogin,
                'password' => $password,
                'avatar' => $defaultAvatar,
                'status' => AppUser::STATUS_ACTIVE,
                'email_verified_at' => strNowTime(),
            ];

            $user = AppUser::create(array_merge(
                $paramsUser,
                $attrsCreate
            ));
        }

        $user->password = $password;

        $user->updateMetaValue('apple_user_id', $userAppleId);

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
                'link' => str_replace('{screen}','email-verification',env('URL_APP_PAGE',"https://wanderworld.com/app?screen={screen}&token=$token64")),
                'text' => __('auth.continue_to_app'),
            );

            return sendMail((new GenericMail(
                __('auth.recovery_password_title_email'),
                __('auth.recovery_password_description_email'),
                $button,
                Storage::disk(config('voyager.storage.disk'))->url('mails/recovery-mailings.png')
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
                'link' => str_replace('{screen}','recovery-account',env('URL_APP_PAGE',"https://wanderworld.com/app?screen={screen}&token=$token64")),
                'text' => __('auth.continue_to_app'),
            );

            return sendMail((new GenericMail(
                __('auth.recovery_account_title_email'),
                __('auth.recovery_account_description_email'),
                $button,
                Storage::disk(config('voyager.storage.disk'))->url('mails/recovery-mailings.gif')
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

        if (!$token64) {
            throw new WanderException(__('auth.recovery_token_not_valid'));
        }
        
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
                    return !(array_search('administrative_area_level_2', $adrComp['types']) === false);
                });
                if ($addressCompPlace) {
                    return $addressCompPlace;
                }
                
                $addressCompPlace = arrayFind($address_components,function($adrComp) {
                    return !(array_search('administrative_area_level_1', $adrComp['types']) === false);
                });
                if ($addressCompPlace) {
                    return $addressCompPlace;
                }

                $addressCompPlace = arrayFind($address_components,function($adrComp) {
                    return !(array_search('locality', $adrComp['types']) === false);
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

        $user1->friends()->syncWithoutDetaching([ $user2->id => ['status' => AppUser::FRIEND_STATUS_ACTIVE] ]);
        $user2->friends()->syncWithoutDetaching([ $user1->id => ['status' => AppUser::FRIEND_STATUS_ACTIVE] ]);

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
            if($page <= -1) {
                throw new WanderException(__('app.something_was_wrong'));
            }
        }

        $items = $items instanceof Collection ? $items : Collection::make($items);

        $startIdx = ($page * $perPage) - $perPage;

        $total = $items->count();

        $sliceItems = collect($items->slice($startIdx, $perPage)->values());

        $pagination = new \Illuminate\Pagination\LengthAwarePaginator($sliceItems, $total, $perPage, $page);
        return $pagination;
    }
}


if (!function_exists('showImage')) {
    /**
     * @param \Illuminate\Support\Collection $items
     * @param int $perPage
     * @param int|null $page
     * @return String|null
     */
    function showImage($image) {
        try {
            if(!(strpos($image, 'https:') === false)) {
                return file_get_contents($image);
            }
            return Storage::disk(config('voyager.storage.disk'))->response($image);
        } catch(\League\Flysystem\FileNotFoundException $fnf) {
            return Storage::disk(config('voyager.storage.disk'))->url($image);
        }
    }
}


function getOptimizedImage($pathImage) {

    try {
        /*if(file_exists($pathImage)) {
            $pathImage = file_get_contents($pathImage);
            //throw new WanderException(__('auth.image_not_found'));
        }*/
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.tinify.com/shrink',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $pathImage,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Basic '.env('API_KEY_TINYPNG', 'YXBpOnNkUkxOSzI1cXBuSm15eWxDRG5XanJLVzd4VFc5S1JI'),
                'Content-Type: image/jpeg'
            ),
        ));

        $response = curl_exec($curl);

        $httpcode = intval( curl_getinfo($curl, CURLINFO_HTTP_CODE) );

        curl_close($curl);
        if($httpcode < 200 || $httpcode > 299) {
            throw new WanderException(__('auth.image_not_found'));
            //logActivity('no optimized');
            //return false;
        }
        return json_decode($response);
    } catch (\Exception $e) {
        return null;
    }
}

function getRandomName($urlImage) {

    try {
        $partsUrl = parse_url($urlImage);
        $ext = pathinfo($partsUrl['path'], PATHINFO_EXTENSION);
        if(!$ext) {
            return null;
        }
        $baseName = uniqid("IMG".date('Ymd'));
        return "$baseName.$ext";
    } catch (\Exception $e) {
        return null;
    }
}


/**
 * Read countries json
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












function sanitize_title( $title, $fallback_title = '', $context = 'save' ) {
    $raw_title = $title;
 
    if ( 'save' === $context ) {
        $title = remove_accents( $title );
    }
 
    if ( '' === $title || false === $title ) {
        $title = $fallback_title;
    }
 
    return $title;
}
function remove_accents( $string ) {
    if ( ! preg_match( '/[\x80-\xff]/', $string ) ) {
        return $string;
    }
 
    if ( seems_utf8( $string ) ) {
        $chars = array(
            // Decompositions for Latin-1 Supplement.
            'ª' => 'a',
            'º' => 'o',
            'À' => 'A',
            'Á' => 'A',
            'Â' => 'A',
            'Ã' => 'A',
            'Ä' => 'A',
            'Å' => 'A',
            'Æ' => 'AE',
            'Ç' => 'C',
            'È' => 'E',
            'É' => 'E',
            'Ê' => 'E',
            'Ë' => 'E',
            'Ì' => 'I',
            'Í' => 'I',
            'Î' => 'I',
            'Ï' => 'I',
            'Ð' => 'D',
            'Ñ' => 'N',
            'Ò' => 'O',
            'Ó' => 'O',
            'Ô' => 'O',
            'Õ' => 'O',
            'Ö' => 'O',
            'Ù' => 'U',
            'Ú' => 'U',
            'Û' => 'U',
            'Ü' => 'U',
            'Ý' => 'Y',
            'Þ' => 'TH',
            'ß' => 's',
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ã' => 'a',
            'ä' => 'a',
            'å' => 'a',
            'æ' => 'ae',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ð' => 'd',
            'ñ' => 'n',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ö' => 'o',
            'ø' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ü' => 'u',
            'ý' => 'y',
            'þ' => 'th',
            'ÿ' => 'y',
            'Ø' => 'O',
            // Decompositions for Latin Extended-A.
            'Ā' => 'A',
            'ā' => 'a',
            'Ă' => 'A',
            'ă' => 'a',
            'Ą' => 'A',
            'ą' => 'a',
            'Ć' => 'C',
            'ć' => 'c',
            'Ĉ' => 'C',
            'ĉ' => 'c',
            'Ċ' => 'C',
            'ċ' => 'c',
            'Č' => 'C',
            'č' => 'c',
            'Ď' => 'D',
            'ď' => 'd',
            'Đ' => 'D',
            'đ' => 'd',
            'Ē' => 'E',
            'ē' => 'e',
            'Ĕ' => 'E',
            'ĕ' => 'e',
            'Ė' => 'E',
            'ė' => 'e',
            'Ę' => 'E',
            'ę' => 'e',
            'Ě' => 'E',
            'ě' => 'e',
            'Ĝ' => 'G',
            'ĝ' => 'g',
            'Ğ' => 'G',
            'ğ' => 'g',
            'Ġ' => 'G',
            'ġ' => 'g',
            'Ģ' => 'G',
            'ģ' => 'g',
            'Ĥ' => 'H',
            'ĥ' => 'h',
            'Ħ' => 'H',
            'ħ' => 'h',
            'Ĩ' => 'I',
            'ĩ' => 'i',
            'Ī' => 'I',
            'ī' => 'i',
            'Ĭ' => 'I',
            'ĭ' => 'i',
            'Į' => 'I',
            'į' => 'i',
            'İ' => 'I',
            'ı' => 'i',
            'Ĳ' => 'IJ',
            'ĳ' => 'ij',
            'Ĵ' => 'J',
            'ĵ' => 'j',
            'Ķ' => 'K',
            'ķ' => 'k',
            'ĸ' => 'k',
            'Ĺ' => 'L',
            'ĺ' => 'l',
            'Ļ' => 'L',
            'ļ' => 'l',
            'Ľ' => 'L',
            'ľ' => 'l',
            'Ŀ' => 'L',
            'ŀ' => 'l',
            'Ł' => 'L',
            'ł' => 'l',
            'Ń' => 'N',
            'ń' => 'n',
            'Ņ' => 'N',
            'ņ' => 'n',
            'Ň' => 'N',
            'ň' => 'n',
            'ŉ' => 'n',
            'Ŋ' => 'N',
            'ŋ' => 'n',
            'Ō' => 'O',
            'ō' => 'o',
            'Ŏ' => 'O',
            'ŏ' => 'o',
            'Ő' => 'O',
            'ő' => 'o',
            'Œ' => 'OE',
            'œ' => 'oe',
            'Ŕ' => 'R',
            'ŕ' => 'r',
            'Ŗ' => 'R',
            'ŗ' => 'r',
            'Ř' => 'R',
            'ř' => 'r',
            'Ś' => 'S',
            'ś' => 's',
            'Ŝ' => 'S',
            'ŝ' => 's',
            'Ş' => 'S',
            'ş' => 's',
            'Š' => 'S',
            'š' => 's',
            'Ţ' => 'T',
            'ţ' => 't',
            'Ť' => 'T',
            'ť' => 't',
            'Ŧ' => 'T',
            'ŧ' => 't',
            'Ũ' => 'U',
            'ũ' => 'u',
            'Ū' => 'U',
            'ū' => 'u',
            'Ŭ' => 'U',
            'ŭ' => 'u',
            'Ů' => 'U',
            'ů' => 'u',
            'Ű' => 'U',
            'ű' => 'u',
            'Ų' => 'U',
            'ų' => 'u',
            'Ŵ' => 'W',
            'ŵ' => 'w',
            'Ŷ' => 'Y',
            'ŷ' => 'y',
            'Ÿ' => 'Y',
            'Ź' => 'Z',
            'ź' => 'z',
            'Ż' => 'Z',
            'ż' => 'z',
            'Ž' => 'Z',
            'ž' => 'z',
            'ſ' => 's',
            // Decompositions for Latin Extended-B.
            'Ș' => 'S',
            'ș' => 's',
            'Ț' => 'T',
            'ț' => 't',
            // Euro sign.
            '€' => 'E',
            // GBP (Pound) sign.
            '£' => '',
            // Vowels with diacritic (Vietnamese).
            // Unmarked.
            'Ơ' => 'O',
            'ơ' => 'o',
            'Ư' => 'U',
            'ư' => 'u',
            // Grave accent.
            'Ầ' => 'A',
            'ầ' => 'a',
            'Ằ' => 'A',
            'ằ' => 'a',
            'Ề' => 'E',
            'ề' => 'e',
            'Ồ' => 'O',
            'ồ' => 'o',
            'Ờ' => 'O',
            'ờ' => 'o',
            'Ừ' => 'U',
            'ừ' => 'u',
            'Ỳ' => 'Y',
            'ỳ' => 'y',
            // Hook.
            'Ả' => 'A',
            'ả' => 'a',
            'Ẩ' => 'A',
            'ẩ' => 'a',
            'Ẳ' => 'A',
            'ẳ' => 'a',
            'Ẻ' => 'E',
            'ẻ' => 'e',
            'Ể' => 'E',
            'ể' => 'e',
            'Ỉ' => 'I',
            'ỉ' => 'i',
            'Ỏ' => 'O',
            'ỏ' => 'o',
            'Ổ' => 'O',
            'ổ' => 'o',
            'Ở' => 'O',
            'ở' => 'o',
            'Ủ' => 'U',
            'ủ' => 'u',
            'Ử' => 'U',
            'ử' => 'u',
            'Ỷ' => 'Y',
            'ỷ' => 'y',
            // Tilde.
            'Ẫ' => 'A',
            'ẫ' => 'a',
            'Ẵ' => 'A',
            'ẵ' => 'a',
            'Ẽ' => 'E',
            'ẽ' => 'e',
            'Ễ' => 'E',
            'ễ' => 'e',
            'Ỗ' => 'O',
            'ỗ' => 'o',
            'Ỡ' => 'O',
            'ỡ' => 'o',
            'Ữ' => 'U',
            'ữ' => 'u',
            'Ỹ' => 'Y',
            'ỹ' => 'y',
            // Acute accent.
            'Ấ' => 'A',
            'ấ' => 'a',
            'Ắ' => 'A',
            'ắ' => 'a',
            'Ế' => 'E',
            'ế' => 'e',
            'Ố' => 'O',
            'ố' => 'o',
            'Ớ' => 'O',
            'ớ' => 'o',
            'Ứ' => 'U',
            'ứ' => 'u',
            // Dot below.
            'Ạ' => 'A',
            'ạ' => 'a',
            'Ậ' => 'A',
            'ậ' => 'a',
            'Ặ' => 'A',
            'ặ' => 'a',
            'Ẹ' => 'E',
            'ẹ' => 'e',
            'Ệ' => 'E',
            'ệ' => 'e',
            'Ị' => 'I',
            'ị' => 'i',
            'Ọ' => 'O',
            'ọ' => 'o',
            'Ộ' => 'O',
            'ộ' => 'o',
            'Ợ' => 'O',
            'ợ' => 'o',
            'Ụ' => 'U',
            'ụ' => 'u',
            'Ự' => 'U',
            'ự' => 'u',
            'Ỵ' => 'Y',
            'ỵ' => 'y',
            // Vowels with diacritic (Chinese, Hanyu Pinyin).
            'ɑ' => 'a',
            // Macron.
            'Ǖ' => 'U',
            'ǖ' => 'u',
            // Acute accent.
            'Ǘ' => 'U',
            'ǘ' => 'u',
            // Caron.
            'Ǎ' => 'A',
            'ǎ' => 'a',
            'Ǐ' => 'I',
            'ǐ' => 'i',
            'Ǒ' => 'O',
            'ǒ' => 'o',
            'Ǔ' => 'U',
            'ǔ' => 'u',
            'Ǚ' => 'U',
            'ǚ' => 'u',
            // Grave accent.
            'Ǜ' => 'U',
            'ǜ' => 'u',
        );
 
        // Used for locale-specific rules.
        $locale = get_locale();
 
        if ( in_array( $locale, array( 'de_DE', 'de_DE_formal', 'de_CH', 'de_CH_informal', 'de_AT' ), true ) ) {
            $chars['Ä'] = 'Ae';
            $chars['ä'] = 'ae';
            $chars['Ö'] = 'Oe';
            $chars['ö'] = 'oe';
            $chars['Ü'] = 'Ue';
            $chars['ü'] = 'ue';
            $chars['ß'] = 'ss';
        } elseif ( 'da_DK' === $locale ) {
            $chars['Æ'] = 'Ae';
            $chars['æ'] = 'ae';
            $chars['Ø'] = 'Oe';
            $chars['ø'] = 'oe';
            $chars['Å'] = 'Aa';
            $chars['å'] = 'aa';
        } elseif ( 'ca' === $locale ) {
            $chars['l·l'] = 'll';
        } elseif ( 'sr_RS' === $locale || 'bs_BA' === $locale ) {
            $chars['Đ'] = 'DJ';
            $chars['đ'] = 'dj';
        }
 
        $string = strtr( $string, $chars );
    } else {
        $chars = array();
        // Assume ISO-8859-1 if not UTF-8.
        $chars['in'] = "\x80\x83\x8a\x8e\x9a\x9e"
            . "\x9f\xa2\xa5\xb5\xc0\xc1\xc2"
            . "\xc3\xc4\xc5\xc7\xc8\xc9\xca"
            . "\xcb\xcc\xcd\xce\xcf\xd1\xd2"
            . "\xd3\xd4\xd5\xd6\xd8\xd9\xda"
            . "\xdb\xdc\xdd\xe0\xe1\xe2\xe3"
            . "\xe4\xe5\xe7\xe8\xe9\xea\xeb"
            . "\xec\xed\xee\xef\xf1\xf2\xf3"
            . "\xf4\xf5\xf6\xf8\xf9\xfa\xfb"
            . "\xfc\xfd\xff";
 
        $chars['out'] = 'EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy';
 
        $string              = strtr( $string, $chars['in'], $chars['out'] );
        $double_chars        = array();
        $double_chars['in']  = array( "\x8c", "\x9c", "\xc6", "\xd0", "\xde", "\xdf", "\xe6", "\xf0", "\xfe" );
        $double_chars['out'] = array( 'OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th' );
        $string              = str_replace( $double_chars['in'], $double_chars['out'], $string );
    }
 
    return $string;
}
function get_locale() {
    global $locale;
 
    if ( isset( $locale ) ) {
        return $locale;
    }
 
    if ( empty( $locale ) ) {
        $locale = 'en_US';
    }
 
    /**
     * Filters the locale ID of the WordPress installation.
     *
     * @since 1.5.0
     *
     * @param string $locale The locale ID.
     */
    return $locale;
}