<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
//use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Exceptions\WanderException;
use App\Exceptions\ChatException;

use Tymon\JWTAuth\Contracts\JWTSubject;


class AppUser extends \TCG\Voyager\Models\User implements JWTSubject
{
    use Notifiable, HasFactory;


    const STATUS_PENDING    = '1';
    const STATUS_ACTIVE     = '2';
    const STATUS_INACTIVE   = '3';

    const FRIEND_STATUS_PENDING          = '1';
    const FRIEND_STATUS_ACTIVE           = '2';
    const FRIEND_STATUS_BLOCKED          = '3';
    const FRIEND_STATUS_MUTED            = '4';
    const FRIEND_STATUS_BLOCKED_REQUESTS = '5';

    const DEFAULT_AVATAR = 'users/default_avatar.png';
    

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    /*protected $fillable = [
        'name',
        'email',
        'nickname',
        'avatar',
        'status',
        'continent_code',
        'country_code',
        'city_gplace_id',
        'email_verified_at',
        'password',
        'settings',
    ];*/

    protected $guarded = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    /*protected $casts = [
        'email_verified_at' => 'datetime',
    ];*/

    protected $dates = [
        'email_verified_at',
    ];

    protected $appends = [
        'chat_user_id',
        'chat_key',
        'city_name',
        'country_name',
        'is_default_avatar'
        //'level',
    ];

    public $numberOfFriendsRequests;

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Return all user's comments
     * @return hasMany
     */
    public function comments() {
        return $this->hasMany(Comment::class,'user_id');
    }

    /**
     * Return all user's meta values
     * @return hasMany
     */
    public function metas() {
        return $this->hasMany(AppUserMeta::class,'user_id');
    }


    /**
     * Return all user's request invitations
     * @return hasMany
     */
    public function myInvitations() {
        return $this->hasMany(Invitation::class,'user_id');
    }


    /**
     * Return user's pending request invitations
     * @return hasMany
     */
    public function myPendingInvitations() {
        return $this->hasMany(Invitation::class,'user_id')->whereIn('status', [
            Invitation::STATUS_PENDING,
        ]);
    }

    /**
     * Return all user's invitations
     * @return hasMany
     */
    public function invitations() {
        return $this->hasMany(Invitation::class,'invited_id');
    }

    /**
     * Return user's pending invitations
     * @return hasMany
     */
    public function pendingInvitations() {
        return $this->hasMany(Invitation::class,'invited_id')->whereIn('status', [
            Invitation::STATUS_PENDING,
        ]);
    }

    /**
     * Return user's reported photos
     * @return belongsToMany
     */
    public function myReportedPhotos() {
        return $this->belongsToMany(Photo::class, 'photo_report', 'user_id', 'photo_id');
    }

    /**
     * ToArray function
     */
    public function toArray() {
        $request = request();
        $myAppends = [
            //'path' => $request->path(),
        ];
        if( $request->is('api/auth/me') ) {
            //$myAppends['number_of_friend_requests'] = $this->getNumberOfFriendRelationshipInvitations();
            $myAppends['number_of_friend_requests'] = 0;
            $myAppends['completed_profile'] = $this->getMetaValue('info_public_saved') === 'yes' && $this->getMetaValue('info_private_saved') === 'yes' ? 'yes' : 'no';
            $myAppends['times_change_city'] = $this->getTimesChangeCity();
        }

        if( 
            $request->is('api/auth/me/friends') ||  
            $request->is('api/auth/me/visit-recommendations') 
        ) {
            $user = auth('api')->user();
            $myAppends['has_any_travel'] = $this->hasAnyFinishedTravel($user);
        }
        
        if( 
            $request->is('api/auth/me/common-friends/*') ||
            $request->is('api/services/v1/search-connections') ||
            $request->is('api/auth/me/friends-level2/reduced')
        ) {
            $temp = array_merge($this->attributesToArray(), $this->relationsToArray(), $myAppends);
            $re = [];
            foreach($temp as $key=>$item) {
                if(!in_array($key,['id','name','avatar','city_name','country_code','country_name','level','city_gplace_id'])) {
                    continue;
                }
                $re[$key] = $item;
            }
            return $re;
        }
        
        return array_merge($this->attributesToArray(), $this->relationsToArray(), $myAppends);
    }

    /**
     * Check if has any finished travel with friend
     * @param AppUser $friend
     * @return bool
     */
    public function hasAnyFinishedTravel($friend) {
        $tblName = (new Travel())->getTable();

        $exists1 = DB::table($tblName)
                        ->where('user_id', $this->id)
                        ->where('host_id', $friend->id)
                        ->where('status', Travel::STATUS_FINISHED)->exists();

        $exists2 = DB::table($tblName)
                        ->where('user_id', $friend->id)
                        ->where('host_id', $this->id)
                        ->where('status', Travel::STATUS_FINISHED)->exists();

        return $exists1 || $exists2 || $this->isMyFriend($friend, false);
    }

    /**
     * Return all user's friends
     * @return belongsToMany
     */
    public function friends() {
        return $this->belongsToMany(AppUser::class,'friends','user_id','friend_id')->withPivot('status');
    }

    /**
     * Return user's active friends
     * @return belongsToMany
     */
    public function activeFriends() {
        return $this->belongsToMany(AppUser::class,'friends','user_id','friend_id')
                        ->withPivot('status')
                        ->wherePivotIn('status', [
                            self::FRIEND_STATUS_ACTIVE,
                            self::FRIEND_STATUS_MUTED,
                            self::FRIEND_STATUS_BLOCKED_REQUESTS,
                        ]);
    }

    /**
     * Return user's active friends by level relation
     * @return Collection
     */
    public function activeFriendsLevel($level = 1) {
        if($level === 1) {
            return $this->activeFriends()->get();
        }
        $list = collect([]);
        $this->reActiveFriendsLevel($level, 0, $this, $list);
        return $list;
    }

    /**
     * Return user's active friends by level relation (recursive)
     * @return belongsToMany
     */
    public function reActiveFriendsLevel($levelReached, $level, $user, &$list) {
        if($level === $levelReached) {
            return;
        }
        $meId = $this->id;

        $userFriends = $user->activeFriends()->get();
        foreach($userFriends as $friend) {
            $found = $list->search(function ($f, $key) use ($friend, $meId) {
                return $f->id === $friend->id;
            });
            if($found === false && $meId !== $friend->id) {
                $friend->level = $level + 1;
                $list->push($friend);
            }
        }

        foreach($userFriends as $friend) {
            $this->reActiveFriendsLevel($levelReached, $level + 1, $friend, $list);
        }
    }

    /**
     * Function created for voyager conflict or something related
     */
    public function appUsers() {
        return $this->friends();
    }

    /**
     * Return user's travels
     * @return hasMany
     */
    public function travels() {
        return $this->hasMany(Travel::class,'user_id');
    }

    /**
     * Return user's travels
     * @return hasMany
     */
    public function hostTravels() {
        return $this->hasMany(Travel::class,'host_id');
    }

    /**
     * Return user's finished travels
     * @return hasMany
     */
    public function finishedTravels() {
        return $this->hasMany(Travel::class,'user_id')
                        ->whereIn('status', [
                            //Travel::STATUS_ACCEPTED,
                            Travel::STATUS_FINISHED,
                        ]);
    }

    /**
     * Return user's accepted travels
     * @return hasMany
     */
    public function acceptedTravels() {
        return $this->hasMany(Travel::class,'user_id')
                        ->whereIn('status', [
                            Travel::STATUS_ACCEPTED,
                        ]);
    }

    /**
     * Return user's schedule travels
     * @return hasMany
     */
    public function scheduleTravels() {
        return $this->hasMany(Travel::class,'user_id')
                        ->whereIn('status', [
                            Travel::STATUS_ACCEPTED,
                            Travel::STATUS_PENDING,
                            Travel::STATUS_CANCELLED,
                            Travel::STATUS_REJECTED,
                        ]);
    }

    /**
     * Return user's pending travels
     * @return hasMany
     */
    public function pendingTravels() {
        return $this->hasMany(Travel::class,'host_id')
                        ->whereIn('status', [
                            Travel::STATUS_PENDING,
                        ]);
    }

    /**
     * Return user's request travels where I am the host
     * @return hasMany
     */
    public function requestsTravels() {
        return $this->hasMany(Travel::class,'host_id')
                        ->whereIn('status', [
                            Travel::STATUS_ACCEPTED,
                            Travel::STATUS_PENDING,
                        ]);
    }

    /**
     * Return user's request travels where I requested someone to be my host
     * @return hasMany
     */
    public function meRequestsTravels() {
        return $this->hasMany(Travel::class,'user_id')
                        ->whereIn('status', [
                            Travel::STATUS_ACCEPTED,
                            Travel::STATUS_PENDING,
                        ]);
    }


    /**
     * Return user's accepted travels with additional relationship information
     * @return hasMany
     */
    public function acceptedTravelsWithExtra() {
        $modelUser = AppUser::with(['acceptedTravels' => function($relationTravel){
            $relationTravel->with('activeAlbums');
            $relationTravel->with('host');
            $relationTravel->with('contacts');
        }])->find($this->id);
        return $modelUser->acceptedTravels;
    }

    /**
     * Return user's schedule travels with additional relationship information
     * @return hasMany
     */
    public function scheduleTravelsWithExtra() {
        $modelUser = AppUser::with(['scheduleTravels' => function($relationTravel){
            $relationTravel->with('activeAlbums');
            $relationTravel->with('host');
            $relationTravel->with('contacts');
        }])->find($this->id);
        return $modelUser->scheduleTravels;
    }
    

    /**
     * Return user's requests travels with additional relationship information
     * @return hasMany
     */
    public function requestsTravelsWithExtra() {
        $modelUser = AppUser::with(['requestsTravels' => function($relationTravel){
            $relationTravel->with('activeAlbums');
            $relationTravel->with('user');
            $relationTravel->with('host');
            $relationTravel->with('contacts');
        }])->find($this->id);
        return $modelUser->requestsTravels;
    }


    /**
     * Return user's recommendations
     * @return hasMany
     */
    public function myRecommendations() {
        return $this->hasMany(Recommendation::class,'user_id');
    }

    /**
     * Return user's recommendations
     * @return hasMany
     */
    public function visitRecommendations() {
        return $this->hasMany(Recommendation::class,'invited_id');
    }

    /**
     * Return user's interests
     * @return hasMany
     */
    public function myInterests() {
        $tblName = 'app_user_interests';
        return DB::table($tblName)->select('interest_id')->where('user_id',$this->id)->get()->pluck('interest_id');
    }

    /**
     * Update user's interests
     * @return 
     */
    public function updateMyInterests($interests_ids) {
        $tblName = 'app_user_interests';

        DB::table($tblName)->where('user_id', $this->id)->delete();

        $insert = array();
        foreach($interests_ids as $interest_id) {
            $id = intval($interest_id);
            if(findInArray($id, $insert, 'interest_id') === false) {
                $insert[] = array(
                    'user_id' => $this->id,
                    'interest_id' => $id,
                );

            }
        }

        return DB::table($tblName)->insert($insert);
    }

    /**
     * Return user's idioms
     * @return hasMany
     */
    public function myLanguages() {
        $tblName = 'app_user_languages';
        return DB::table($tblName)->select('language_id')->where('user_id',$this->id)->get()->pluck('language_id');
    }

    /**
     * Update user's idioms
     * @return 
     */
    public function updateMyLanguages($languages_ids) {
        $tblName = 'app_user_languages';

        DB::table($tblName)->where('user_id', $this->id)->delete();

        $insert = array();
        foreach($languages_ids as $language_id) {
            $id = intval($language_id);
            if(findInArray($id, $insert, 'language_id') === false) {
                $insert[] = array(
                    'user_id' => $this->id,
                    'language_id' => $id,
                );

            }
        }

        return DB::table($tblName)->insert($insert);
    }
    

    /**
     * Attribute function
     * Get chat_key
     * @return String
     */
    public function getChatKeyAttribute() {
        if(empty($this->chat_user_id)) {
            throw new ChatException(__('app.chat_connection_error'));
        }
        $metaValue = $this->getMetaValue('chat_key');
        if(empty($metaValue)) {
            return $this->refreshChatKey();
        }
        return $metaValue;
    }

    /**
     * Attribute function
     * Get chat_user_id
     * @return String
     */
    public function getChatUserIdAttribute() {
        $metaValue = $this->getMetaValue('chat_user_id');
        if(empty($metaValue)) {
            return $this->refreshChatUserId();
        }
        return $metaValue;
    }

    /**
     * Get user's city name
     * @return String
     */
    public function getCityNameAttribute() {
        $metaValue = $this->getMetaValue('city_name');
        $metaCityGId = $this->getMetaValue('city_gplace_id');
        if(empty($metaValue) || empty($metaCityGId) || $metaCityGId !== $this->city_gplace_id) {
            return $this->refreshCityName();
        }
        return $metaValue;
    }

    /**
     * Get user's country name
     * @return String
     */
    public function getCountryNameAttribute() {
        $coutries = readJsonCountries();
        $idxFound = findInArray($this->country_code,$coutries,'country_code');

        if( $idxFound === false ) {
            return $this->country_code;
        }
        return $coutries[$idxFound]['name'];
    }

    /**
     * Get user's country name
     * @return String
     */
    public function getNameAttribute($name) {
        $request = request();
        if( $request->is('admin/app-users/*') || $request->is('admin/app-users') ) {
            return $name;
        }
        return !empty($this->nickname) ? $this->nickname : $name;
    }

    /**
     * Get user's is_default_avatar attribute
     * @return String
     */
    public function getIsDefaultAvatarAttribute($name) {
        return $this->getMetaValue('is_default_avatar', 'no');
    }

    /**
     * Get user's public name
     * @return String
     */
    public function getPublicName($name = null) {
        return !empty($this->nickname) ? $this->nickname : $this->name;
    }
    

    /**
     * 
     */
    public function showAvatar() {
        return showImage($this->avatar);
    }

    /**
     * Get user's level
     * @return Integer
     */
    /*public function getLevelAttribute() {
        return 1;
    }*/

    /**
     * Get user meta value
     * @return String
     */
    public function getMetaValue($key, $defaultVal = null) {
        $meta = $this->metas()->where('meta_key',$key)->first();
        if(!$meta || is_null($meta->meta_value)) {
            return $defaultVal;
        }
        return $meta->meta_value;
    }
    

    /**
     * Generate a valid string to be use as a login user name for tinode backend
     * @return String
     */
    public static function generateChatId() {
        $a_z = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $login_policy = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_0123456789";

        $idx = rand(0,51);
        
        $firstLetter = $a_z[$idx];

        $part2Login = '';
        $len = rand(2, 31);
        for($i = 0 ; $i < $len ; $i++) {
            $idx2 = rand(0,62);
            $part2Login .= $login_policy[$idx2];
        }

        return $firstLetter.$part2Login;
    }

    /**
     * Generate a valid cid attribute to be use for any user 
     */
    public static function getChatId() {

        $model = new AppUser();
        $tblName = $model->getTable();

        $cid = self::generateChatId();
        $exists = DB::table($tblName)->where('cid',$cid)->exists();

        while($exists) {
            $cid = self::generateChatId();
            $exists = DB::table($tblName)->where('cid',$cid)->exists();
        }
        return $cid;
    }

    /**
     * Get chat_user_token meta
     * @return String
     */
    public function getChatUserToken() {
        $val = $this->getMetaValue('chat_user_token');
        if(empty($val)) {
            throw new ChatException(__('app.chat_connection_error'));
        }
        return $val;
    }


    /**
     * Update the user's chat_key (password chat)
     */
    public function refreshChatKey($times = 1) {
        
        try {

            if($times > 3) {
                throw new ChatException(__('app.chat_connection_error'));
            }

            $newkey = Str::random(50);

            $urlWanbox = env('APP_ENV','local') === 'production' ? env('WANBOX_MIDDLEWARE_URL', '') : env('TEST_WANBOX_MIDDLEWARE_URL', '');
            $apiKeyMiddleware = env('TOKEN_WANBOX_MIDDLEWARE', '');

            $uid = "usr{$this->chat_user_id}";

            $response = Http::withHeaders([
                'Authorization' => "Basic $apiKeyMiddleware",
            ])->put("$urlWanbox/api/chatusers/$uid", [
                'user_id' => $this->id,
                'place' => $this->city_name.' / '.$this->country_name,
                'user_name' => $this->getPublicName(),
                'user_login' => $this->cid,
                'user_password' => $newkey,
                'user_email' => $this->email,
                'user_token' => $this->getChatUserToken(),
            ]);

            if(!$response->successful()) {
                sleep(2);
                return $this->refreshChatKey($times + 1);
            }
            return $newkey;
        } catch (\Illuminate\Http\Client\ConnectionException $th) {
            throw new WanderException(__('app.connection_error'));
        }
    }

    /**
     * Update the user's chat data account (password chat)
     */
    public function updateChatDataAccount($times = 1) {
        
        try {

            if($times > 3) {
                throw new ChatException(__('app.chat_connection_error'));
            }

            $urlWanbox = env('APP_ENV','local') === 'production' ? env('WANBOX_MIDDLEWARE_URL', '') : env('TEST_WANBOX_MIDDLEWARE_URL', '');
            $apiKeyMiddleware = env('TOKEN_WANBOX_MIDDLEWARE', '');

            $response = Http::withHeaders([
                'Authorization' => "Basic $apiKeyMiddleware",
            ])->put("$urlWanbox/api/chatusers/{$this->chat_user_id}", [
                'user_id' => $this->id,
                'place' => $this->city_name.' / '.$this->country_name,
                'user_name' => $this->getPublicName(),
                'user_login' => $this->cid,
                'user_password' => $this->chat_key,
                'user_email' => $this->email,
                'user_token' => $this->getChatUserToken(),
            ]);

            if(!$response->successful()) {
                sleep(2);
                return $this->updateChatDataAccount($times + 1);
            }
            return true;
        } catch (\Illuminate\Http\Client\ConnectionException $th) {
            throw new WanderException($th->getMessage());
        }
    }

    private function recursiveRefreshChatUserId($newkey, $times = 1) {

        try {

            if($times > 3) {
                throw new ChatException(__('app.chat_connection_error'));
            }

            $urlWanbox = env('APP_ENV','local') === 'production' ? env('WANBOX_MIDDLEWARE_URL', '') : env('TEST_WANBOX_MIDDLEWARE_URL', '');
            $apiKeyMiddleware = env('TOKEN_WANBOX_MIDDLEWARE', '');

            $response = Http::withHeaders([
                'Authorization' => "Basic $apiKeyMiddleware",
            ])->post("$urlWanbox/api/chatusers", [
                'user_id' => $this->id,
                'place' => $this->city_name.' / '.$this->country_name,
                'user_login' => $this->cid,
                'user_name' => $this->getPublicName(),
                'user_password' => $newkey,
                'user_email' => $this->email,
            ]);
    
            if(!$response->successful()) {
                sleep(2);
                return $this->recursiveRefreshChatUserId($newkey, $times + 1);
                //return $response->json();
            }
            return $response->json();
        } catch (\Illuminate\Http\Client\ConnectionException $th) {
            throw new WanderException(__('app.connection_error'));
        } catch (ChatException $ce) {
            throw new ChatException($ce->getMessage());
        } catch (WanderException $we) {
            throw new WanderException($we->getMessage());
        } catch (\Exception $e) {
            logActivity($e->getMessage());
            throw new WanderException(__('app.something_was_wrong'));
        }
    }

    /**
     * Update the user's chat_user_id key of the database. Also it's updated the chat_key (password chat) 
     * and other important data to perform the login in tinode backend
     */
    public function refreshChatUserId() {

        try {

            DB::beginTransaction();

            $newkey = Str::random(50);

            $arrayData = $this->recursiveRefreshChatUserId($newkey);

            //logActivity($arrayData);
            
            $this->updateMetaValue('chat_user_id',$arrayData['data']['uid']);
            $this->updateMetaValue('chat_user_token',$arrayData['data']['token']);
            $this->updateMetaValue('chat_key',$newkey);
            
            DB::commit();

            return $arrayData['data']['uid'];
        } catch (\Illuminate\Http\Client\ConnectionException $th) {
            DB::rollback();
            throw new WanderException(__('app.connection_error'));
        } catch (ChatException $ce) {
            throw new ChatException($ce->getMessage());
        } catch (WanderException $we) {
            DB::rollback();
            throw new WanderException($we->getMessage());
        } catch (\Exception $e) {
            DB::rollback();
            logActivity($e->getMessage());
            throw new WanderException(__('app.something_was_wrong'));
        }
    }

    
    /**
     * Update user meta value
     */
    public function updateMetaValue($key, $value) {
        $meta = AppUserMeta::where('user_id',$this->id)
                    ->where('meta_key',$key)
                    ->first();
        
        if(empty($meta)) {
            $meta = new AppUserMeta();
            $meta->user_id = $this->id;
            $meta->meta_key = $key;
        }

        $meta->meta_value = is_array($value) || is_object($value) ? json_encode($value) : $value;
        
        if(!$meta->save()) {
            throw new WanderException(__('app.connection_error'));
        }
        return true;
    }


    /**
     * Update the value of user's city name
     */
    public function refreshCityName() {


        try {

            DB::beginTransaction();

            $placeId = $this->city_gplace_id;

            if(empty($placeId)) {
                return null;
            }
            $lang = app()->getLocale();

            $googleKey = setting('admin.google_maps_key', env('GOOGLE_KEY', ''));

            $response = Http::get("https://maps.googleapis.com/maps/api/geocode/json?place_id=$placeId&key=$googleKey&language=$lang");
    
            if(!$response->successful()) {
                throw new WanderException(__('app.connection_error'));
            }
            $arrayData = $response->json();

            if($arrayData['status'] !== 'OK') {
                throw new WanderException(__('app.connection_error'));
            }

            $place = reset($arrayData['results']);

            $address_components = $place['address_components'];

            $city = getGeoPlaceName($address_components,'city');

            if(!$city) {
                throw new WanderException(__('app.connection_error'));
            }
            
            $this->updateMetaValue('city_name',$city['long_name']);
            $this->updateMetaValue('city_gplace_id',$placeId);
            
            DB::commit();

            return $city['long_name'];
        } catch (\Illuminate\Http\Client\ConnectionException $th) {
            DB::rollback();
            throw new WanderException(__('app.connection_error'));
        } catch (ChatException $ce) {
            throw new ChatException($ce->getMessage());
        } catch (WanderException $we) {
            DB::rollback();
            throw new WanderException($we->getMessage());
        } catch (\Exception $e) {
            DB::rollback();
            logActivity($e->getMessage());
            throw new WanderException(__('app.something_was_wrong'));
        }
    }


    private function updateChatTopicStatus($myFriend, $action, $times = 1) {
        if($times > 3) {
            return null;
        }
        $urlWanbox = env('APP_ENV','local') === 'production' ? env('WANBOX_MIDDLEWARE_URL', '') : env('TEST_WANBOX_MIDDLEWARE_URL', '');
        $apiKeyMiddleware = env('TOKEN_WANBOX_MIDDLEWARE', '');


        $chat_user_id = $myFriend->chat_user_id;

        $paramsRequestChat = [
            'action' => $action,
            'friend_id' => $chat_user_id,
            'user_login' => $this->cid,
            'user_password' => $this->chat_key,
            'user_email' => $this->email,
        ];

        //logActivity($paramsRequestChat);

        $response = Http::withHeaders([
            'Authorization' => "Basic $apiKeyMiddleware",
        ])->put("$urlWanbox/api/chattopics", $paramsRequestChat);

        if(!$response->successful()) {

            if( $response->getStatusCode() == 500 ) {
                $this->updateChatTopicStatus($myFriend, $action, $times + 1);
                return;
                //throw new ChatException(__('app.no_action_no_ini_conversation'));
            }
            //throw new ChatException(__('app.chat_connection_error').' '.$response->getStatusCode());
        }
    }

    /**
     * Update friend relationship in the database and the tinode backend
     */
    public function updateFriendRelationship($action,$friend_id) {
         

        try {

            DB::beginTransaction();


            $friends = $this->friends();

            $myFriend = $friends->find($friend_id);
        
            if(empty($myFriend)) {
                $activeFriends2 = $this->activeFriendsLevel( 2 );
                $hostFoundIndex = $activeFriends2->search(function ($appUser) use ($friend_id) {
                    return $appUser->id === $friend_id;
                });

                if($hostFoundIndex === false) {
                    throw new WanderException(__('app.no_friend_selected'));
                }

                $myFriend = $activeFriends2->get($hostFoundIndex);

                if(!$myFriend) {
                    throw new WanderException(__('app.no_friend_selected'));
                }
            }

            switch ($action) {
                case 'mute':
                case 'unmute':
                    $this->updateChatTopicStatus($myFriend, $action, $friend_id);
                    break;
            }
            

            if($this->isMyFriend($myFriend)) {
                switch ($action) {
                    case 'mute':
                        $friends->updateExistingPivot($myFriend->id, ['status' => AppUser::FRIEND_STATUS_MUTED]);
                        break;
                    case 'block':
                        $friends->updateExistingPivot($myFriend->id, ['status' => AppUser::FRIEND_STATUS_BLOCKED_REQUESTS]);
                        break;
                    case 'unmute':
                    case 'unblock':
                        $friends->updateExistingPivot($myFriend->id, ['status' => AppUser::FRIEND_STATUS_ACTIVE]);
                        break;
                    case 'delete':
                        brokeFriendRelationship($this,$myFriend);
                        break;
                    
                    default:
                        break;
                }
            } else {
                switch ($action) {
                    case 'mute':
                        $this->updateRelationshipStatusLevel2($myFriend, AppUser::FRIEND_STATUS_MUTED);
                        break;
                    case 'block':
                        $this->updateRelationshipStatusLevel2($myFriend, AppUser::FRIEND_STATUS_BLOCKED_REQUESTS);
                        break;
                    case 'unmute':
                    case 'unblock':
                        $this->updateRelationshipStatusLevel2($myFriend, AppUser::FRIEND_STATUS_ACTIVE);
                        break;
                    
                    default:
                        break;
                }
            }

            
            
            DB::commit();

            return true;
        } catch (\Illuminate\Http\Client\ConnectionException $th) {
            DB::rollback();
            throw new WanderException(__('app.connection_error'));
        } catch (ChatException $ce) {
            throw new ChatException($ce->getMessage());
        } catch (WanderException $we) {
            DB::rollback();
            throw new WanderException($we->getMessage());
        } catch (\Exception $e) {
            DB::rollback();
            logActivity($e->getMessage());
            throw new WanderException(__('app.something_was_wrong'));
        }
    }

    /**
     * Find User by email or phone or facebook id
     */
    public static function findPendingByEmailOrPhoneOrFbid($invitedEmail, $invitedPhone, $invitedFbid = null) {

        $invitedEmail = getStrFakeVal($invitedEmail);
        $invited = self::where('email', $invitedEmail)->first();

        if(!$invited) {

            if($invitedFbid) {

                $invitedFbid = getStrFakeVal($invitedFbid);

                $list = AppUserMeta::where('meta_key','facebook_user_id')
                    ->where('meta_value',$invitedFbid)
                    ->get();

                if($list->count() === 1) {
                    return self::find($list->first()->user_id);
                }

            }

            $invitedPhone = getStrFakeVal($invitedPhone);

            $list = AppUserMeta::where('meta_key','phone')
                    ->where('meta_value',$invitedPhone)
                    ->get();
            $count = $list->count();
            if($count > 1) {
                throw new WanderException(__('app.email_necessary_invitation'));   
            }
            if($count === 0) {
                return null;
            }

            $invited = self::find($list->first()->user_id);
        }

        return $invited;
    }

    /**
     * Check if exists any invitation with the user information
     */
    public function syncInvitations() {
        $user = $this;
        
        $pendingInvitations = Invitation::whereNull('invited_id')
                    ->where('status', Invitation::STATUS_PENDING)
                    ->where(function($query) use ($user) {
                        $query->where('invited_email', $user->email)
                                ->orWhere('invited_phone', getStrFakeVal($user->getMetaValue('phone')));
                    })->get();

        $user->invitations()->saveMany($pendingInvitations);

    }


    /**
     * Update the number of contacts of the user pending friend relationship invitations
     * when a new friend relationship is created
     * @param AppUser $myNewFriend
     */
    public function refreshInvitationsContactsForAdding($myNewFriend) {
        $myPendingInvitations = $this->pendingInvitations()->get();

        foreach($myPendingInvitations as $invitation) {
            if( areFriends($invitation->user,$myNewFriend) ) {
                $info = parseStrToJson($invitation->invited_info, (new \stdClass));
                $n = getAtrValue($info, 'numberContacts', 0);
                $info->numberContacts = $n + 1;
                $invitation->invited_info = json_encode($info);
                if(!$invitation->save()) {
                    throw new WanderException(__('app.connection_error'));
                }
            }
        }
    }

    /**
     * Update the number of contacts of the user pending friend relationship invitations
     * when a new friend relationship is delete
     * @param AppUser $myNewFriend
     */
    public function refreshInvitationsContactsForDeleting($oldFriend) {
        $myPendingInvitations = $this->myPendingInvitations()->get();

        foreach($myPendingInvitations as $invitation) {
            if( areFriends($invitation->invited,$oldFriend) ) {
                $info = parseStrToJson($invitation->invited_info, (new \stdClass));
                $n = getAtrValue($info, 'numberContacts', 0);
                $info->numberContacts = $n - 1;
                $invitation->invited_info = json_encode($info);
                if(!$invitation->save()) {
                    throw new WanderException(__('app.connection_error'));
                }
            }
        }
    }

    /**
     * Get number of user's friend relationship invitations
     * @return int
     */
    public function getNumberOfFriendRelationshipInvitations() {
        return $this->pendingInvitations()->get()->count();
    }

    /**
     * Get profile data
     */
    public function getProfileInfo() {
        $bundle = new \stdClass;
        
        $requestTravels = $this->requestsTravels();

        $bundle->id = $this->id;
        $bundle->times_host = $requestTravels->whereIn('request_type', [Travel::RTYPE_HOST, Travel::RTYPE_HOST_GUIDER])->count();;
        $bundle->times_guider = $requestTravels->whereIn('request_type', [Travel::RTYPE_GUIDER, Travel::RTYPE_HOST_GUIDER])->count();
        $bundle->number_travels = $this->finishedTravels()->count();

        $request = request();
        if( $request->is('api/services/v1/friends/*/profile') ) {
            $bundle->name = $this->name;
        } else {
            $bundle->name = $this->getRawOriginal('name');
        }

        $bundle->nickname = $this->nickname;
        
        $bundle->email = $this->email;
        $bundle->is_email_private = $this->getMetaValue('is_email_private', 'no');

        $bundle->image = $this->avatar;
        $bundle->is_avatar_private = $this->getMetaValue('is_avatar_private', 'no');
        $bundle->is_default_avatar = $this->getMetaValue('is_default_avatar', 'no');
        

        $bundle->aboutme = $this->getMetaValue('about_me', '');
        $bundle->is_aboutme_private = $this->getMetaValue('is_aboutme_private', 'no');

        $bundle->interests = $this->getMetaValue('my_interests', []);
        if(\isJsonString($bundle->interests)) {
            $bundle->interests = \parseStrToJson($bundle->interests);
        }
        $bundle->interests_ids = $this->myInterests();
        $bundle->is_interests_private = $this->getMetaValue('is_interests_private', 'no');

        $bundle->languages = $this->getMetaValue('my_languages', []);
        if(\isJsonString($bundle->languages)) {
            $bundle->languages = \parseStrToJson($bundle->languages);
        }
        $bundle->languages_ids = $this->myLanguages();
        $bundle->is_languages_private = $this->getMetaValue('is_languages_private', 'no');

        $bundle->birthday = $this->getMetaValue('birthday', null);
        $bundle->is_birthday_private = $this->getMetaValue('is_birthday_private', 'no');

        $bundle->country_code = $this->country_code;
        $bundle->city_name = $this->city_name;

        $bundle->gender = $this->getMetaValue('gender', null);
        $bundle->is_gender_private = $this->getMetaValue('is_gender_private', 'no');

        $bundle->personal_status = $this->getMetaValue('personal_status', null);
        $bundle->is_personal_status_private = $this->getMetaValue('is_personal_status_private', 'no');

        $bundle->phone = $this->getMetaValue('phone', null);
        $bundle->phone_dial = $this->getMetaValue('phone_dial', null);
        $bundle->phone_number = $this->getMetaValue('phone_number', null);
        $bundle->is_phone_private = $this->getMetaValue('is_phone_private', 'no');

        return $bundle;
    }

    /**
     * Return number of times which it was changed the city
     * @return int
     */
    public function getTimesChangeCity() {
        return intval($this->getMetaValue(Carbon::now('UTC')->format('YYYY').'_times_change_city', 0));
    }

    /**
     * Return if an user is my friend
     * @return bool
     */
    public function isMyFriend($user, $onlyActives = true) {
        if(!$onlyActives) {
            return !!($this->friends()->find($user->id) || $user->id === $this->id);    
        }
        return !!($this->activeFriends()->find($user->id) || $user->id === $this->id);
    }


    /**
     * Get common friends
     * @return Collection
     */
    public function getCommonContacts($contactUser) {

        $myFriendsIds = $this->activeFriendsLevel( 2 )->pluck('id');
        $commons = $contactUser->activeFriendsLevel( 2 )->whereIn('id',$myFriendsIds);

        return collect($commons->values());
    }

    /**
     * Function to get status between to friends level 2
     * @return string
     */
    public function getRelationshipStatusLevel2($user) {

        //$row1 = DB::table('friends_status')->where('user_id',$user->id)
        //                                    ->where('friend_id',$this->id)->first();

        $row2 = DB::table('friends_status')->where('user_id',$this->id)
                                            ->where('friend_id',$user->id)->first();

        //if(!$row1 && !$row2) {
        if(!$row2) {
            return self::FRIEND_STATUS_ACTIVE;
        }

        /*if($row1) {
            return $row1->status;
        }*/

        return $row2->status;
    }

    /**
     * Function to update status between to friends level 2
     * @return bool
     */
    public function updateRelationshipStatusLevel2($friend, $status) {

        //$row1 = DB::table('friends_status')->where('user_id',$friend->id)
        //                                    ->where('friend_id',$this->id)->first();

        $row2 = DB::table('friends_status')->where('user_id',$this->id)
                                            ->where('friend_id',$friend->id)->first();

        //if(!$row1 && !$row2) {
        if(!$row2) {
            return DB::table('friends_status')
                ->updateOrInsert(
                    [
                        'user_id' => $this->id, 
                        'friend_id' => $friend->id
                    ],
                    ['status' => $status]
            );
        }

        /*
        if($row1) {
            return DB::table('friends_status')
                ->updateOrInsert(
                    [
                        'user_id' => $friend->id, 
                        'friend_id' => $this->id
                    ],
                    ['status' => $status]
            );
        }*/

        return DB::table('friends_status')
                ->updateOrInsert(
                    [
                        'user_id' => $this->id, 
                        'friend_id' => $friend->id
                    ],
                    ['status' => $status]
            );
    }


    /**
     * 
     * @return AppUser
     */
    public function getFriendByFacebookId($facebook_id) {
        $activeFriends = $this->activeFriends()->get();

        $filtered = $activeFriends->filter(function ($friend, $key) use ($facebook_id) {
            return $friend->getMetaValue('facebook_user_id') === $facebook_id;
        });

        if($filtered->count() === 1) {
            return $filtered->first();
        }
        return null;
    }

    /**
     * 
     * @return AppUser
     */
    public static function findUserByFacebookId($facebook_id) {
        $facebook_id = getStrFakeVal($facebook_id);

        $list = AppUserMeta::where('meta_key','facebook_user_id')
            ->where('meta_value',$facebook_id)
            ->get();

        if($list->count() === 1) {
            return self::find($list->first()->user_id);
        }
        return null;
    }
}
