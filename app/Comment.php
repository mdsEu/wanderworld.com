<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use App\Traits\ParseDates;

class Comment extends Model
{
    use ParseDates;
    
    protected $guarded = [];

    public function user() {
        return $this->belongsTo(AppUser::class,'user_id');
    }
}
