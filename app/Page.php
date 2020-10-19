<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use TCG\Voyager\Traits\Translatable;

class Page extends Model
{
    use SoftDeletes, Translatable;

    protected $translatable = ['title', 'content'];

    protected static function boot(){
        parent::boot();
        static::addGlobalScope(new \App\Scopes\ModelActiveScope);
    }
}
