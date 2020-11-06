<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
//use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use App\Exceptions\WanderException;

use Tymon\JWTAuth\Contracts\JWTSubject;


class AppUser extends \TCG\Voyager\Models\User implements JWTSubject
{
    use Notifiable, HasFactory;


    const STATUS_PENDING    = '1';
    const STATUS_ACTIVE     = '2';
    const STATUS_INACTIVE   = '3';

    const FRIEND_STATUS_PENDING    = '1';
    const FRIEND_STATUS_ACTIVE     = '2';
    const FRIEND_STATUS_BLOCKED    = '3';
    const FRIEND_STATUS_MUTED      = '4';
    

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

    public function toArray() {
        $request = request();
        $myAppends = [
            //'path' => $request->path(),
        ];
        if( $request->is('api/auth/me') ) {
            $myAppends['numberOfFriendRequests'] = $this->getNumberOfFriendRelationshipInvitations();
            $myAppends['completed_profile'] = $this->getMetaValue('info_public_saved') === 'yes' && $this->getMetaValue('info_private_saved') === 'yes' ? 'yes' : 'no';
        }
        return array_merge($this->attributesToArray(), $this->relationsToArray(), $myAppends);
    }

    /**
     * Return all user's friends
     * @return belongsToMany
     */
    public function friends() {
        return $this->belongsToMany(AppUser::class,'friends','user_id','friend_id');
    }

    /**
     * Return user's active friends
     * @return belongsToMany
     */
    public function activeFriends() {
        return $this->belongsToMany(AppUser::class,'friends','user_id','friend_id')
                        //->withPivot('status')
                        ->wherePivotIn('status', [
                            self::FRIEND_STATUS_ACTIVE,
                            self::FRIEND_STATUS_MUTED
                        ]);
    }

    /**
     * Function created for voyager conflict or something related
     */
    public function appUsers() {
        return $this->friends();
    }

    /**
     * Attribute function
     * Get chat_key
     * @return String
     */
    public function getChatKeyAttribute() {
        if(empty($this->chat_user_id)) {
            throw new WanderException(__('app.chat_connection_error'));
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
            return null;
        }
        return $coutries[$idxFound]['name'];
    }

    /**
     * Get user's public name
     * @return String
     */
    public function getPublicName() {
        return $this->nickname;
    }

    /**
     * Get user meta value
     * @return String
     */
    public function getMetaValue($key, $defaultVal = null) {
        $meta = $this->metas()->where('meta_key',$key)->first();
        if(!$meta) {
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
        $meta = $this->metas()->where('meta_key','chat_user_token')->first();
        if(empty($meta)) {
            throw new WanderException(__('app.chat_connection_error'));
        }
        return $meta->meta_value;
    }


    /**
     * Update the user's chat_key (password chat)
     */
    public function refreshChatKey() {
        
        try {

            $newkey = Str::random(50);

            $urlWanbox = env('APP_ENV','local') === 'production' ? env('WANBOX_MIDDLEWARE_URL', '') : env('TEST_WANBOX_MIDDLEWARE_URL', '');
            $apiKeyMiddleware = env('TOKEN_WANBOX_MIDDLEWARE', '');

            $uid = "usr{$this->chat_user_id}";

            $response = Http::withHeaders([
                'Authorization' => "Basic $apiKeyMiddleware",
            ])->put("$urlWanbox/api/chatusers/$uid", [
                'user_login' => $this->cid,
                'user_password' => $newkey,
                'user_email' => $this->email,
                'user_token' => $this->getChatUserToken(),
            ]);

            if(!$response->successful()) {
                throw new WanderException(__('app.chat_connection_error'));
            }
            return $newkey;
        } catch (\Illuminate\Http\Client\ConnectionException $th) {
            throw new WanderException(__('app.connection_error'));
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

            $urlWanbox = env('APP_ENV','local') === 'production' ? env('WANBOX_MIDDLEWARE_URL', '') : env('TEST_WANBOX_MIDDLEWARE_URL', '');
            $apiKeyMiddleware = env('TOKEN_WANBOX_MIDDLEWARE', '');

            $response = Http::withHeaders([
                'Authorization' => "Basic $apiKeyMiddleware",
            ])->post("$urlWanbox/api/chatusers", [
                'user_login' => $this->cid,
                'user_name' => $this->getPublicName(),
                'user_password' => $newkey,
                'user_email' => $this->email,
            ]);
    
            if(!$response->successful()) {
                throw new WanderException(__('app.chat_connection_error'));
            }
            $arrayData = $response->json();

            logActivity(var_export($arrayData,true));
            
            $this->updateMetaValue('chat_user_id',$arrayData['data']['uid']);
            $this->updateMetaValue('chat_user_token',$arrayData['data']['token']);
            $this->updateMetaValue('chat_key',$newkey);
            
            DB::commit();

            return $arrayData['data']['uid'];
        } catch (\Illuminate\Http\Client\ConnectionException $th) {
            DB::rollback();
            throw new WanderException(__('app.connection_error'));
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

        $meta->meta_value = $value;
        
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
     * Update friend relationship in the database and the tinode backend
     */
    public function updateFriendRelationship($action,$friend_id) {
         

        try {

            DB::beginTransaction();

            $urlWanbox = env('APP_ENV','local') === 'production' ? env('WANBOX_MIDDLEWARE_URL', '') : env('TEST_WANBOX_MIDDLEWARE_URL', '');
            $apiKeyMiddleware = env('TOKEN_WANBOX_MIDDLEWARE', '');

            $friends = $this->friends();

            $myFriend = $friends->find($friend_id);

            if(empty($myFriend)) {
                throw new WanderException(__('app.no_friend_selected'));
            }

            $chat_user_id = $myFriend->chat_user_id;

            $paramsRequestChat = [
                'action' => $action,
                'friend_id' => $chat_user_id,
                'user_login' => $this->cid,
                'user_password' => $this->chat_key,
                'user_email' => $this->email,
            ];

            logActivity($paramsRequestChat);

            $response = Http::withHeaders([
                'Authorization' => "Basic $apiKeyMiddleware",
            ])->put("$urlWanbox/api/chattopics", $paramsRequestChat);
    
            if(!$response->successful()) {

                if( $response->status() == 404 ) {
                    throw new WanderException(__('app.no_action_no_ini_conversation'));
                }
                throw new WanderException(__('app.chat_connection_error'));
            }

            switch ($action) {
                case 'mute':
                    $friends->updateExistingPivot($myFriend->id, ['status' => AppUser::FRIEND_STATUS_MUTED]);
                    break;
                case 'block':
                    $friends->updateExistingPivot($myFriend->id, ['status' => AppUser::FRIEND_STATUS_BLOCKED]);
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
            
            DB::commit();

            return true;
        } catch (\Illuminate\Http\Client\ConnectionException $th) {
            DB::rollback();
            throw new WanderException(__('app.connection_error'));
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

                $list = AppUserMeta::where('meta_key','facebook_user_id')
                    ->where('meta_value',$invitedFbid)
                    ->get();

                if($list->count() === 1) {
                    return self::find($list->first());
                }

            }



            $list = AppUserMeta::where('meta_key','phone')
                    ->where('meta_value',$invitedPhone)
                    ->get();

            if($list->count() !== 1) {
                return null;
            }

            $invited = self::find($list->first());
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
        
        $bundle->aboutme = $this->getMetaValue('about_me', '');
        $bundle->is_aboutme_private = $this->getMetaValue('is_aboutme_private', 'no');

        $bundle->interests = $this->getMetaValue('my_interests', []);
        $bundle->is_interests_private = $this->getMetaValue('is_interests_private', 'no');

        $bundle->languages = $this->getMetaValue('my_languages', []);
        $bundle->is_languages_private = $this->getMetaValue('is_languages_private', 'no');

        $bundle->birthday = $this->getMetaValue('birthday', null);
        $bundle->is_birthday_private = $this->getMetaValue('is_birthday_private', 'no');

        $bundle->country_code = $this->country_code;
        $bundle->city_name = $this->city_name;

        $bundle->gender = $this->getMetaValue('gender', null);
        $bundle->is_gender_private = $this->getMetaValue('is_gender_private', 'no');

        $bundle->personal_status = $this->getMetaValue('personal_status', null);
        $bundle->is_gender_private = $this->getMetaValue('is_gender_private', 'no');

        $bundle->phone = $this->getMetaValue('phone', null);
        $bundle->is_phone_private = $this->getMetaValue('is_phone_private', 'no');

        return $bundle;
    }
}
