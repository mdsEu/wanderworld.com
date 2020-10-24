<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppUserMeta extends Model
{
    use HasFactory;

    protected $table = 'app_users_meta';

    public $timestamps = false;

}
