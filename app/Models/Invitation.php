<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use App\Exceptions\WanderException;
use App\Mail\GenericMail;

class Invitation extends Model
{
    use HasFactory;


    const STATUS_PENDING     = '1';
    const STATUS_ACCEPTED    = '2';
    const STATUS_REJECTED    = '3';
    const STATUS_CREATED     = '4';

    protected $guarded = [];

    public function user() {
        return $this->belongsTo(AppUser::class,'user_id');
    }

    public function invited() {
        return $this->belongsTo(AppUser::class,'invited_id');
    }

    public function toArray() {
        $myAppend = [];
        return array_merge($this->attributesToArray(), $this->relationsToArray(), $myAppend);
    }

    public function getPhone() {
        $invited = $this->invited;
        if($invited) {
            return $invited->getMetaValue('phone', $this->invited_email);
        }
        return $this->invited_email;
    }

    public function getEmail() {
        $invited = $this->invited;
        if($invited) {
            return $invited->email;
        }
        return $this->invited_email;
    }

    public function sendNotification($action) {

        switch ($action) {
            case 'sms':
                
                return false;
            case 'email':
                $email = $this->getEmail();
                if(empty($email)) {
                    return false;
                }
                $button = array(
                    'link' => secure_url("app/invitation"),
                    'text' => __('auth.continue_to_app'),
                );
                $userPName = $this->user->getPublicName();
                return sendMail((new GenericMail(
                    __('xx:Invitation'),
                    __('xx::user has invited you to be friends in Wander World', ['user' => $userPName]),
                     $button
                ))->subject(__('xx::user has invited you', ['user' => $userPName]))
                    ->to($email));
                break;
            
            default:
                return false;
        }

    }

    public static function findPendingByEmailOrPhone($user_id, $email, $phone) {

        $email = getStrFakeVal($email);
        $phone = getStrFakeVal($phone);

        $invitation = self::where('user_id', $user_id)
            ->where('status', self::STATUS_PENDING)
            ->where(function($query) use ($email, $phone){
                $query->where('invited_email', $email)
                    ->orWhere('invited_phone', $phone);
            })
            ->first();
        return $invitation;
    }

    /**
     * Create a Friend relationship
     */
    public function createFriendRelationship() {
        if(is_null($this->user) || is_null($this->invited)) {
            throw new WanderException( __('xx:The requirementes to create a friend relationship are not accomplished.') );
        }

        $this->user->friends()->syncWithoutDetaching($this->invited, ['status' => AppUser::FRIEND_STATUS_ACTIVE]);
        $this->invited->friends()->syncWithoutDetaching($this->user, ['status' => AppUser::FRIEND_STATUS_ACTIVE]);



        return true;
    }

    /**
     * Notify the both users about the current status of the invitation
     */
    public function notifyUsersStatus() {
        if(is_null($this->user) || is_null($this->invited)) {
            throw new WanderException( __('xx:No users to notify.') );
        }
        /**
         * To DO
         */
        switch ($this->status) {
            case self::STATUS_ACCEPTED:

                break;
            case self::STATUS_REJECTED:
                break;
            default:
                throw new WanderException(__('xx:Action not valid'));
        }
    }

}
