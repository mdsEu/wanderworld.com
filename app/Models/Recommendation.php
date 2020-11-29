<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recommendation extends Model
{
    use HasFactory;

    
    protected $guarded = [];
    
    protected $table = 'recommendations';

    public function user() {
        return $this->belongsTo(AppUser::class,'user_id');
    }

    public function invited() {
        return $this->belongsTo(AppUser::class,'invited_id');
    }

    public function travel() {
        return $this->belongsTo(Travel::class,'travel_id');
    }

}
