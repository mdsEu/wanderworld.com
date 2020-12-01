<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;
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
            return $invited->getMetaValue('phone', $this->invited_phone);
        }
        return $this->invited_phone;
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
                $keyNRSGateway = setting('admin.nrs_gateway_apikey', env('NRS_GATEWAY_APIKEY', ""));

                $tplMessage = setting('admin.sms_msg_invitation_be_friends', "");

                if(empty($tplMessage)) {
                    return false;
                }

                $tplMessage = \str_replace('{USER_NAME}', $this->user->getPublicName(), $tplMessage);

                $urlRestNrsGatewaySendMessage = setting('admin.endpoint_sendmessage_nrsgateway', "https://gateway.plusmms.net/rest/message");

                $client = new Client();
                $guzzleRes = $client->post($urlRestNrsGatewaySendMessage, [
                    'headers' => [
                        'Authorization' => "Basic $keyNRSGateway",
                        'Content-Type' => "application/json",
                    ], 
                    'json' => [
                        'to' => [$this->getPhone()],
                        'text' => $tplMessage,
                        'from' => "WanderWorld",
                    ],
                ]);

                $code = $guzzleRes->getStatusCode();

                if(!($code >= Response::HTTP_OK && $code < Response::HTTP_MULTIPLE_CHOICES)) {
                    return false;
                }
                
                return true;
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
                    __('notification.title_invitation'),
                    __('notification.user_has_invited', ['user' => $userPName]),
                    $button
                ))->subject(__('notification.subject_user_has_invited', ['user' => $userPName]))
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
            throw new WanderException( __('app.no_able_be_friends') );
        }

        makeFriendRelationship($this->user,$this->invited);
        
        return true;
    }

    /**
     * Notify the both users about the current status of the invitation
     */
    public function notifyUsersStatus() {
        if(is_null($this->user) || is_null($this->invited)) {
            throw new WanderException( __('app.no_able_be_friends') );
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
                throw new WanderException(__('app.action_not_valid'));
        }
    }

}
