<?php

namespace App\Http\Middleware;

use Closure;

class ModelActiveMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $request->merge(['scope_only_actived' => 'yes']);
        return $next($request);
    }
}
