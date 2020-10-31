<?php

namespace App\Observers;

use App\Models\AppUser;

class AppUserObserver
{
    /**
     * Handle the app user "created" event.
     *
     * @param  \App\Models\AppUser  $appUser
     * @return void
     */
    public function created(AppUser $appUser)
    {
        $appUser->syncInvitations();
    }

    /**
     * Handle the app user "updated" event.
     *
     * @param  \App\Models\AppUser  $appUser
     * @return void
     */
    public function updated(AppUser $appUser)
    {
        //
    }

    /**
     * Handle the app user "deleted" event.
     *
     * @param  \App\Models\AppUser  $appUser
     * @return void
     */
    public function deleted(AppUser $appUser)
    {
        //
    }

    /**
     * Handle the app user "restored" event.
     *
     * @param  \App\Models\AppUser  $appUser
     * @return void
     */
    public function restored(AppUser $appUser)
    {
        //
    }

    /**
     * Handle the app user "force deleted" event.
     *
     * @param  \App\Models\AppUser  $appUser
     * @return void
     */
    public function forceDeleted(AppUser $appUser)
    {
        //
    }
}
