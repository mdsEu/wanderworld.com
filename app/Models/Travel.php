<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Travel extends Model
{
    use HasFactory, SoftDeletes;


    const RTYPE_HOST        = 'H';
    const RTYPE_HOST_GUIDER = 'HG';
    const RTYPE_GUIDER      = 'G';

    const STATUS_PENDING        = '1';
    const STATUS_ACCEPTED       = '2';
    const STATUS_REJECTED       = '3';
    const STATUS_CANCELLED      = '4';
    const STATUS_FINISHED       = '5';
    const STATUS_REMOVED        = '6';

    protected $guarded = [];
    
    protected $table = 'travels';

    public function user() {
        return $this->belongsTo(AppUser::class,'user_id');
    }

    public function host() {
        return $this->belongsTo(AppUser::class,'host_id');
    }

    public function contacts() {
        return $this->hasMany(TravelContact::class,'travel_id');
    }

    public function albums() {
        return $this->hasMany(Album::class,'travel_id');
    }

    public function activeAlbums() {
        return $this->hasMany(Album::class,'travel_id')->whereIn('status', [
            Album::STATUS_ACCEPTED,
            Album::STATUS_REPORTED,
        ]);
    }

    public function recommendations() {
        return $this->hasMany(Recommendation::class,'travel_id');
    }

    public function notifyAcceptHostRequest() {
        /**
         * To DO
         */
    }

    public function notifyRejectHostRequest() {
        /**
         * To DO
         */
    }
}
