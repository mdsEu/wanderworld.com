<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
//use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Tymon\JWTAuth\Contracts\JWTSubject;


class AppUser extends \TCG\Voyager\Models\User implements JWTSubject
{
    use Notifiable;


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

    public function friends() {
        return $this->belongsToMany(AppUser::class,'friends','user_id','friend_id')
                        //->withPivot('status')
                        ->wherePivotIn('status', [
                            self::FRIEND_STATUS_ACTIVE,
                            self::FRIEND_STATUS_BLOCKED,
                            self::FRIEND_STATUS_MUTED
                        ]);
    }
}
