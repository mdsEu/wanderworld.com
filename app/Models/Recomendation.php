<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recomendation extends Model
{
    use HasFactory;

    
    protected $guarded = [];
    
    protected $table = 'recomendations';

    public function user() {
        return $this->belongsTo(AppUser::class,'user_id');
    }

    public function invited() {
        return $this->belongsTo(AppUser::class,'invited_id');
    }

}
