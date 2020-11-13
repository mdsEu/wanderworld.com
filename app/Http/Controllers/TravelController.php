<?php

namespace App\Http\Controllers;

use App\Exceptions\WanderException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Models\AppUser;
use App\Models\Invitation;

class TravelController extends Controller
{
    public $guard;

    public function __construct()
    {
        //$this->middleware('auth:api', ['except' => ['login']]);
        $this->guard = 'api';
    }


    /**
     * Return common friend of the user logged with other user
     */
    public function sendHostRequest(Request $request) {
        try {


            $params = $request->only([
                'host_id',
                'start',
                'end',
                'resquest_type',
            ]);

            $validaYMDDate = function ($attribute, $value, $fail) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}/',$value)) {
                    $fail(__('xx:Date format not valid'));
                    return;
                }
            };

            $rules = [
                'start' => [$validaYMDDate],
                'end' => [$validaYMDDate],
                'request_type' => [
                    'required',
                    Rule::in([
                        Travel::RTYPE_HOST,
                        Travel::RTYPE_HOST_GUIDER,
                        Travel::RTYPE_GUIDER,
                    ]),
                ],
            ] ;

            $validator = Validator::make($params, $rules);

            if ($validator->fails()) {
                return sendResponse(null,$validator->messages(),false);
            }

            $user = auth($this->guard)->user();

            $host_id = $request->get('host_id', null);

            $host = $user->activeFriends()->find($friend_id);

            if(!$host) {
                throw new WanderException(__('xx:In this moment this friend is not accepting host or guide requests'));
            }

            if($host->pivot->status === AppUser::FRIEND_STATUS_BLOCKED_REQUESTS) {
                throw new WanderException(__('xx:In this moment this friend is not accepting host or guide requests'));
            }

            $startDate = Carbon::createFromFormat('Y-m-d',$params['start']);
            $endDate = Carbon::createFromFormat('Y-m-d',$params['end']);

            $now = Carbon::now('UTC');

            if($now->diffInDays($startDate) <= 0) {
                throw new WanderException(__('xx:Dates range selecction not valid'));
            }
            if($startDate->diffInDays($endDate) <= 0) {
                throw new WanderException(__('xx:Dates range selecction not valid'));
            }


            $travel = Travel::create([
                'user_id' => $user->id,
                'host_id' => $host->id,
                'start_at' => $startDate->format('Y-m-d'),
                'end_at' => $endDate->format('Y-m-d'),
                'request_type' => $params['request_type'],
                'status' => Travel::TRAVEL_STATUS_PENDING,
            ]);

            if(!$travel) {
                throw new WanderException(__('xx:It was not posible to process the request. Try again'));
            }

            $contacts = $request->get('contacts', []);

            if( !empty($contacts) && \is_array($contacts) ) {
                $createContacts = array_map(function($contact){
                    
                    $cUser = new \stdClass();
                    $cUser->id = null;
                    $cUser->name = $contact['name'];
                    $cUser->place_name = $contact['place_name'];
                    if($appUser = App::find($contact['id'])) {
                        $cUser->id = $appUser->id;
                        $cUser->name = $appUser->getPublicName();
                        $cUser->place_name = "{$appUser->city_name} / {$appUser->country_name}";
                    }
                    return [
                        'contact_id' => $cUser->id,
                        'name' => $cUser->name,
                        'place_name' => $cUser->place_name,
                    ];
                }, $contacts);
                $travel->contacts()->createMany($createContacts);
            }
            /*
            $commonFriends = array(
                array(
                    'id' => {user id},
                    'name' => "Crisina Rojas",
                    'place_name' => "Rosario / Argentina",
                ),
                array(
                    'id' => {user id},
                    'name' => "Sofia Delgado",
                    'place_name' => "Madrid / España",
                ),
                .
                .
                .
            )
             */
            
            return sendResponse();
        } catch (QueryException $qe) {
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            return sendResponse(null, __('app.data_not_found'), false, $notFoundE);
        } catch (WanderException $we) {
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            return sendResponse(null, __('app.something_was_wrong'), false, $e);
        }
    }
}
