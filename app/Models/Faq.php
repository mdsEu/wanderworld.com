<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use TCG\Voyager\Traits\Translatable;

class Faq extends Model
{
    use Translatable;

    protected $translatable = ['question', 'answer'];

    protected static function boot(){
        parent::boot();
        static::addGlobalScope(new \App\Scopes\ModelActiveScope);
    }
}
