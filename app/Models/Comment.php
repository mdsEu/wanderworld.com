<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\ParseDates;

class Comment extends Model
{
    
    protected $guarded = [];

    public function user() {
        return $this->belongsTo(AppUser::class,'user_id');
    }
}
