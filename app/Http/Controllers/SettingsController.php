<?php

namespace App\Http\Controllers;

use App\Exceptions\WanderException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function filterSettings(Request $request) {
        $settingsNames = $request->get('c',[]);
        if (empty($settingsNames)) {
            return [];
        }

        if( is_string($settingsNames) ) {
            $settingsNames = explode(',',$settingsNames);
        }

        $valsSettings = [];

        foreach($settingsNames as $sName) {
            $valsSettings[$sName] = setting("admin.{$sName}");
        }
        return sendResponse($valsSettings);
    }
}
