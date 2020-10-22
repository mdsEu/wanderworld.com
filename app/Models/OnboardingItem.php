<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use TCG\Voyager\Traits\Translatable;

class OnboardingItem extends Model
{
    use SoftDeletes, Translatable;

    protected $translatable = ['title', 'description'];

    protected static function boot(){
        parent::boot();
        static::addGlobalScope(new \App\Scopes\ModelActiveScope);
    }
}
