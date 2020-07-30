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
            return OnboardingItem::withTranslations()->get();
        } catch (\Exception $th) {
            return [];
        }
    }

    public function getVersion(Request $request) {
        try {
            return sendResponse( setting('admin.appversion', null) );
        } catch (\Exception $th) {
            return null;
        }
    }
}
