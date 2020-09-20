<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\OnboardingItem;

use Illuminate\Support\Facades\App;

class MultiPage extends Controller
{
    //


    public function allOnboardingItems(Request $request) {
        try {
            return sendResponse( OnboardingItem::withTranslations()->get() );
        } catch (\Exception $e) {
            return sendResponse( null, $e->getMessage(), false);
        }
    }

    public function getVersion(Request $request) {
        try {
            return sendResponse( setting('admin.appversion', null) );
        } catch (\Exception $th) {
            return sendResponse( null );
        }
    }
}
