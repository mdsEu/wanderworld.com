<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use TCG\Voyager\Traits\Translatable;

class Interest extends Model
{
    use Translatable;

    protected $translatable = ['name'];

    protected static function boot(){
        parent::boot();
        static::addGlobalScope(new \App\Scopes\ModelActiveScope);
    }
}
