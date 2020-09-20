<?php

namespace App\Http\Middleware;

use Closure;

class SwitchLanguageMiddleware
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

        $lang = $request->get('lang', config('voyager.multilingual.default',null));
        if( $lang ) {
            $locale = app()->setLocale($lang);
        }

        return $next($request);
    }
}
