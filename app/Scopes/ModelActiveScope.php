<?php

namespace App\Scopes;


use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;


class ModelActiveScope implements Scope {

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        if( request('scope_only_actived', 'no') == 'no' ) {
            return;
        }

        $builder->where(function ($query) {
            $query->where('is_active', true);
        });

    }
}
