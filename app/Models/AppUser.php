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

    public function friends() {
        return $this->belongsToMany(AppUser::class,'friends','user_id','friend_id')
                        //->withPivot('status')
                        ->wherePivotIn('status', [
                            self::FRIEND_STATUS_ACTIVE,
                            self::FRIEND_STATUS_BLOCKED,
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
        $meta = $this->metas()->where('meta_key','chat_key')->first();
        if(empty($meta)) {
            return $this->refreshChatKey();
        }
        return $meta->meta_value;
    }

    public function getChatUserIdAttribute() {
        $meta = $this->metas()->where('meta_key','chat_user_id')->first();
        if(empty($meta)) {
            return $this->refreshChatUserId();
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

            $uid = "usr{$user->chat_user_id}";

            $response = Http::withHeaders([
                'Authorization' => "Basic $apiKeyMiddleware",
            ])->put("$urlWanbox/api/chatusers/$uid", [
                'user_login' => $user->cid,
                'user_password' => $newkey,
                'user_email' => $user->email,
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

            DB::transaction();


            $newkey = Str::random(50);

            $urlWanbox = env('APP_ENV','local') === 'production' ? env('WANBOX_MIDDLEWARE_URL', '') : env('TEST_WANBOX_MIDDLEWARE_URL', '');
            $apiKeyMiddleware = env('TOKEN_WANBOX_MIDDLEWARE', '');

            $response = Http::withHeaders([
                'Authorization' => "Basic $apiKeyMiddleware",
            ])->post("$urlWanbox/api/chatusers", [
                'user_login' => $user->cid,
                'user_password' => $newkey,
                'user_email' => $user->email,
            ]);
    
            if(!$response->successful()) {
                throw new WanderException(__('xx:error creating chat account'));
            }
            $arrayData = $response->json();


            $this->updateAppUserMeta('chat_user_id',$arrayData['uid']);
            $this->updateAppUserMeta('chat_user_token',$arrayData['token']);
            $this->updateAppUserMeta('chat_key',$newkey);
            
            DB::commit();

            return $arrayData['uid'];
        } catch (\Illuminate\Http\Client\ConnectionException $th) {
            DB::rollback();
            throw new WanderException(__('xx:connection error'));
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
            AppUserMeta::create([
                'user_id' => $this->id,
                'meta_key' => $key,
                'value' => $value,
            ]);
        }
        $meta->value = $value;
        
        if(!$meta->save()) {
            throw new WanderException(__('xx:connection error updating user meta'));
        }
        return true;
    }
}
