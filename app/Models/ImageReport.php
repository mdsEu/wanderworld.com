<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImageReport extends Model
{
    use HasFactory;

    const REFMODEL_USER  = 'u';
    const REFMODEL_PHOTO = 'p';
    const REFMODEL_NONE  = 'n';

    protected $guarded = [];

    protected $table = 'image_reports';

    /*public function user() {
        return $this->belongsTo(AppUser::class,'user_id');
    }*/

}
