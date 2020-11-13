<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TravelContact extends Model
{
    use HasFactory;


    public $timestamps = false;

    protected $guarded = [];

    public function travel() {
        return $this->belongsTo(Travel::class,'travel_id');
    }

    public function contact() {
        return $this->belongsTo(AppUser::class,'contact_id')->withTrashed();
    }

    public function getNameAttribute($value) {
        if(empty($this->contact)) {
            return $value;
        }
        return $this->contact->getPublicName();
    }

    public function getPlaceNameAttribute($value) {
        if(empty($this->contact)) {
            return $value;
        }
        return "{$this->contact->city_name} / {$this->contact->country_name}";
    }
}
