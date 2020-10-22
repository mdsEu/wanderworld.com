<?php

namespace App\Http\Controllers;

use App\Exceptions\WanderException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Illuminate\Http\Request;
use App\Models\OnboardingItem;
use App\Models\Page;

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

    public function getPage(Request $request, $slug) {
        try {
            return sendResponse( Page::withTranslations()->where('slug',$slug)->first() );
        } catch (\Exception $e) {
            return sendResponse( null, $e->getMessage(), false);
        }
    }
}
