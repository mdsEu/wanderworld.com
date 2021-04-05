<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Http;
use App\Exceptions\WanderException;
use App\Exceptions\ChatException;

use Carbon\Carbon;

class Travel extends Model
{
    use HasFactory;
    //use HasFactory, SoftDeletes;


    const RTYPE_HOST        = 'H';
    const RTYPE_HOST_GUIDER = 'HG';
    const RTYPE_GUIDER      = 'G';

    const STATUS_PENDING        = '1';
    const STATUS_ACCEPTED       = '2';
    const STATUS_REJECTED       = '3';
    const STATUS_CANCELLED      = '4';
    const STATUS_FINISHED       = '5';
    const STATUS_REMOVED        = '6';

    protected $guarded = [];
    
    protected $table = 'travels';

    public function user() {
        return $this->belongsTo(AppUser::class,'user_id');
    }

    public function host() {
        return $this->belongsTo(AppUser::class,'host_id');
    }

    public function contacts() {
        return $this->hasMany(TravelContact::class,'travel_id');
    }

    public function albums() {
        return $this->hasMany(Album::class,'travel_id');
    }

    public function activeAlbums() {
        return $this->hasMany(Album::class,'travel_id')->whereIn('status', [
            Album::STATUS_ACCEPTED,
            Album::STATUS_REPORTED,
        ]);
    }

    public function recommendations() {
        return $this->hasMany(Recommendation::class,'travel_id');
    }

    private function getCustomAutoMessage($withDates = true) {
        switch ($this->request_type) {
            case Travel::RTYPE_HOST_GUIDER:
                return $withDates ? 'app.auto_message_host_guider' : 'app.auto_message_host_guider_optionaly';
            case Travel::RTYPE_GUIDER:
                return $withDates ? 'app.auto_message_guider' : 'app.auto_message_guider_optionaly';
            default:
                return $withDates ? 'app.auto_message_host_guider' : 'app.auto_message_host_guider_optionaly';
        }
    }

    public function notifyAcceptHostRequest($times = 1) {
        /**
         * To DO
         */

        $user = $this->user;
        $host = $this->host;

        try {

            if(!$this->host) {
                return;
            }


            $urlWanbox = env('APP_ENV','local') === 'production' ? env('WANBOX_MIDDLEWARE_URL', '') : env('TEST_WANBOX_MIDDLEWARE_URL', '');
            $apiKeyMiddleware = env('TOKEN_WANBOX_MIDDLEWARE', '');

            $place = $this->getPlaceName();

            if($this->start_at && $this->end_at) {
                
                $startDate = Carbon::createFromFormat('Y-m-d',$this->start_at);
                $endDate = Carbon::createFromFormat('Y-m-d',$this->end_at);
    
                $msgChat = __($this->getCustomAutoMessage(), ['user' => $host->name, 'place' => $place, 'start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]);

            } else {
                $msgChat = __($this->getCustomAutoMessage(false), ['user' => $host->name, 'place' => $place]);
            }
            
            $json = array(
                'txt' => $msgChat,
                'fmt' => [
                    array(
                        'at' => 0,
                        'len' => 0,
                        'tp' => 'IV',
                    )
                ],
            );
            $response = Http::withHeaders([
                'Authorization' => "Basic $apiKeyMiddleware",
            ])->post("$urlWanbox/api/chatmessages", [
                'friend_id' => $host->chat_user_id,
                'message' => json_encode($json),
                'user_login' => $user->cid,
                'user_password' => $user->chat_key,
            ]);

            if(!$response->successful()) {
                if($times > 3) {
                    throw new ChatException(__('app.chat_connection_error'));
                }
                sleep(2);
                $this->notifyAcceptHostRequest($times + 1);
            }
            
        } catch (\Illuminate\Http\Client\ConnectionException $th) {
            throw new WanderException(__('app.connection_error'));
        }

    }

    public function notifyRejectHostRequest() {
        /**
         * To DO
         */
    }

    public function getPlaceName() {
        if($this->host) {
            return $this->host->city_name.' / '.$this->host->country_name;
        }
        $countries = readJsonCountries();

        $idxFoundCountry = findInArray($params['city']['country']['code'], $countries, 'country_code');

        if ($idxFoundCountry === false) {
            return "";
        }
        $foundCountry = $countries[$idxFoundCountry];
        return $foundCountry['name'];
    }
}
