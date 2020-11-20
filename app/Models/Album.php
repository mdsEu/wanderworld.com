<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Album extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_ACCEPTED    = '1';
    const STATUS_REPORTED    = '2';
    const STATUS_BLOCKED     = '3';

    protected $guarded = [];
    protected $appends = [
        'number_of_photos',
        'four_photos',
    ];
    
    protected $table = 'albums';

    public function travel() {
        return $this->belongsTo(Travel::class,'travel_id');
    }

    public function photos() {
        return $this->hasMany(Photo::class,'album_id');
    }

    public function getNumberOfPhotosAttribute() {
        return $this->activePhotos()->count();
    }

    public function getFourPhotosAttribute() {
        return $this->activePhotos()->get()->slice(0, 4);
    }

    public function activePhotos() {
        return $this->hasMany(Photo::class,'album_id')->whereIn('status', [
            self::STATUS_ACCEPTED,
            self::STATUS_REPORTED
        ]);
    }
}
