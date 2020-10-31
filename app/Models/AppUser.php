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
    ];

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

    public function comments() {
        return $this->hasMany(Comment::class,'user_id');
    }

    public function metas() {
        return $this->hasMany(AppUserMeta::class,'user_id');
    }

    public function myInvitations() {
        return $this->hasMany(Invitation::class,'user_id');
    }
    public function invitations() {
        return $this->hasMany(Invitation::class,'invited_id');
    }

    /*public function comun() {
        return 10;
    }*/

    /*public function toArray()
    {
        return array_merge($this->attributesToArray(), $this->relationsToArray(), ['comun' => 10]);
    }*/

    public function friends() {
        return $this->belongsToMany(AppUser::class,'friends','user_id','friend_id');
    }

    public function activeFriends() {
        return $this->belongsToMany(AppUser::class,'friends','user_id','friend_id')
                        //->withPivot('status')
                        ->wherePivotIn('status', [
                            self::FRIEND_STATUS_ACTIVE,
                            self::FRIEND_STATUS_MUTED
                        ]);
    }

    public function appUsers() {
        return $this->friends();
    }

    public function getChatKeyAttribute() {
        if(empty($this->chat_user_id)) {
            throw new WanderException(__('xx::error chat user id'));
        }
        $metaValue = $this->getMetaValue('chat_key');
        if(empty($metaValue)) {
            return $this->refreshChatKey();
        }
        return $metaValue;
    }

    public function getChatUserIdAttribute() {
        $metaValue = $this->getMetaValue('chat_user_id');
        if(empty($metaValue)) {
            return $this->refreshChatUserId();
        }
        return $metaValue;
    }

    public function getCityNameAttribute() {
        $metaValue = $this->getMetaValue('city_name');
        $metaCityGId = $this->getMetaValue('city_gplace_id');
        if(empty($metaValue) || empty($metaCityGId) || $metaCityGId !== $this->city_gplace_id) {
            return $this->refreshCityName();
        }
        return $metaValue;
    }

    public function getPublicName() {
        return $this->nickname;
    }

    public function getMetaValue($key, $defaultVal = null) {
        $meta = $this->metas()->where('meta_key',$key)->first();
        if(!$meta) {
            return $defaultVal;
        }
        return $meta->meta_value;
    }
    

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

    public function getChatUserToken() {
        $meta = $this->metas()->where('meta_key','chat_user_token')->first();
        if(empty($meta)) {
            throw new WanderException(__('xx:this user dont have a chat user token'));
        }
        return $meta->meta_value;
    }

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
                throw new WanderException(__('xx:error updating chat key'));
            }
            return $newkey;
        } catch (\Illuminate\Http\Client\ConnectionException $th) {
            throw new WanderException(__('xx:connection error'));
        }
    }


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
                'user_password' => $newkey,
                'user_email' => $this->email,
            ]);
    
            if(!$response->successful()) {
                throw new WanderException(__('xx:error creating chat account'));
            }
            $arrayData = $response->json();

            logActivity(var_export($arrayData,true));
            
            $this->updateAppUserMeta('chat_user_id',$arrayData['data']['uid']);
            $this->updateAppUserMeta('chat_user_token',$arrayData['data']['token']);
            $this->updateAppUserMeta('chat_key',$newkey);
            
            DB::commit();

            return $arrayData['data']['uid'];
        } catch (\Illuminate\Http\Client\ConnectionException $th) {
            DB::rollback();
            throw new WanderException(__('xx:connection error'));
        } catch (WanderException $we) {
            DB::rollback();
            throw new WanderException($we->getMessage());
        } catch (\Exception $e) {
            DB::rollback();
            logActivity($e->getMessage());
            throw new WanderException(__('xx:something was wrong'));
        }
    }

    public function updateMetaChatKey($value) {
        return $this->updateAppUserMeta('chat_key', $value);
    }

    public function updateAppUserMeta($key, $value) {
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
            throw new WanderException(__('xx:connection error updating user meta'));
        }
        return true;
    }

    public function refreshCityName() {


        try {

            DB::beginTransaction();

            $placeId = $this->city_gplace_id;
            $lang = app()->getLocale();

            $googleKey = setting('admin.google_maps_key', env('GOOGLE_KEY', ''));

            $response = Http::get("https://maps.googleapis.com/maps/api/geocode/json?place_id=$placeId&key=$googleKey&language=$lang");
    
            if(!$response->successful()) {
                throw new WanderException(__('xx:error updating city name'));
            }
            $arrayData = $response->json();

            if($arrayData['status'] !== 'OK') {
                throw new WanderException(__('xx:error updating city name'));
            }

            $place = reset($arrayData['results']);

            $address_components = $place['address_components'];

            $city = getGeoPlaceName($address_components,'city');

            if(!$city) {
                throw new WanderException(__('xx:error updating city name'));
            }
            
            $this->updateAppUserMeta('city_name',$city['long_name']);
            $this->updateAppUserMeta('city_gplace_id',$placeId);
            
            DB::commit();

            return $city['long_name'];
        } catch (\Illuminate\Http\Client\ConnectionException $th) {
            DB::rollback();
            throw new WanderException(__('xx:connection error'));
        } catch (WanderException $we) {
            DB::rollback();
            throw new WanderException($we->getMessage());
        } catch (\Exception $e) {
            DB::rollback();
            logActivity($e->getMessage());
            throw new WanderException(__('xx:something was wrong'));
        }
    }

    public function updateFriendRelationship($action,$friend_id) {
         

        try {

            DB::beginTransaction();

            $urlWanbox = env('APP_ENV','local') === 'production' ? env('WANBOX_MIDDLEWARE_URL', '') : env('TEST_WANBOX_MIDDLEWARE_URL', '');
            $apiKeyMiddleware = env('TOKEN_WANBOX_MIDDLEWARE', '');

            $friends = $this->friends();

            $myFriend = $friends->find($friend_id);

            if(empty($myFriend)) {
                throw new WanderException(__('xx:no friends selected'));
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
                    throw new WanderException(__('xx:its not posible to do this action in this moment. Maybe you dont have initiated a conversation with this friend'));
                }
                throw new WanderException(__('xx:error updating status in tinode'));
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
                    $friends->detach($myFriend->id);
                    break;
                
                default:
                    break;
            }
            
            DB::commit();

            return true;
        } catch (\Illuminate\Http\Client\ConnectionException $th) {
            DB::rollback();
            throw new WanderException(__('xx:connection error '.$th->getMessage()));
        } catch (WanderException $we) {
            DB::rollback();
            throw new WanderException($we->getMessage());
        } catch (\Exception $e) {
            DB::rollback();
            logActivity($e->getMessage());
            throw new WanderException(__('xx:something was wrong'));
        }
    }

    public static function findUserByEmailOrPhone($invitedEmail, $invitedPhone) {

        $invitedEmail = getStrFakeVal($invitedEmail);
        $invited = self::where('email', $invitedEmail)->first();

        if(!$invited) {
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
}
