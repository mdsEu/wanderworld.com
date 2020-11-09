<?php

namespace App\Traits;

use Carbon\Carbon;

trait ParseDates
{

    protected static function parseDate($value) {
        $request = request();
        if( $request->is('api/*') ) {
            return $value;
        }

        $timeAt = Carbon::createFromFormat('Y-m-d H:i:s', $value);
        $timeAt->setTimezone(setting('admin.laravel_time_zone','America/Bogota'));
        return $timeAt->format('Y-m-d H:i:s');
    }

    /*public function getCreatedAtAttribute($value) {
        return self::parseDate($value);
    }

    public function getUpdatedAtAttribute($value) {
        return self::parseDate($value);
    }*/

    /*public function getDeletedAtAttribute($value) {
        return self::parseDate($value);
    }*/
    
}
