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

    const TRAVEL_STATUS_PENDING     = '1';
    const TRAVEL_STATUS_ACCEPTED    = '2';
    const TRAVEL_STATUS_REJECTED    = '3';
    const TRAVEL_STATUS_CANCELLED   = '4';
    const TRAVEL_STATUS_FINISHED    = '5';

    protected $guarded = [];
    
    protected $table = 'travels';

    protected $dates = [
        'start_at',
        'end_at',
    ];

    public function user() {
        return $this->belongsTo(AppUser::class,'user_id');
    }

    public function host() {
        return $this->belongsTo(AppUser::class,'host_id')->withTrashed();
    }

    public function contacts() {
        return $this->hasMany(TravelContact::class,'travel_id')->withTrashed();
    }
}
