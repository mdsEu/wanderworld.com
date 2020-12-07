<?php

namespace App\Http\Controllers;

use App\Exceptions\WanderException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use TCG\Voyager\Facades\Voyager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\AppUser;
use App\Models\Invitation;
use App\Models\Travel;
use App\Models\Photo;
use App\Models\Album;
use App\Models\Recommendation;

class TravelController extends Controller
{
    public $guard;

    public function __construct()
    {
        //$this->middleware('auth:api', ['except' => ['login']]);
        $this->guard = 'api';
    }


    /**
     * Create a travel
     */
    public function sendHostRequestTravel(Request $request) {
        try {


            $params = $request->only([
                'host_id',
                'start',
                'end',
                'request_type',
            ]);

            $rules = [
                'start' => [
                    function ($attribute, $value, $fail) {
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}/',$value)) {
                            $fail(__('app.start_date_travel_not_valid'));
                            return;
                        }
                    }
                ],
                'end' => [
                    function ($attribute, $value, $fail) {
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}/',$value)) {
                            $fail(__('app.end_date_travel_not_valid'));
                            return;
                        }
                    }
                ],
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

            $activeFriends = $user->activeFriendsLevel( 2 );
            $hostFoundIndex = $activeFriends->search(function ($appUser) use ($host_id) {
                return $appUser->id === $host_id;
            });

            if($hostFoundIndex === false) {
                throw new WanderException(__('app.no_accepting_host_request'));
            }

            $host = $activeFriends->get($hostFoundIndex);

            if(!$host) {
                throw new WanderException(__('app.no_accepting_host_request'));
            }
            
            $isMyFriend = $user->isMyFriend($host);
            if($isMyFriend && $host->pivot->status === AppUser::FRIEND_STATUS_BLOCKED_REQUESTS) {
                throw new WanderException(__('app.no_accepting_host_request'));
            }

            if(!$isMyFriend) {
                /**
                 * To DO
                 */
            }

            $startDate = Carbon::createFromFormat('Y-m-d',$params['start']);
            $endDate = Carbon::createFromFormat('Y-m-d',$params['end']);

            $now = Carbon::now('UTC');

            if($now->diffInDays($startDate, false) <= 0) {
                throw new WanderException(__('app.dates_range_not_valid'));
            }
            if($startDate->diffInDays($endDate, false) <= 0) {
                throw new WanderException(__('app.dates_range_not_valid'));
            }


            $travel = Travel::create([
                'user_id' => $user->id,
                'host_id' => $host->id,
                'start_at' => $startDate->format('Y-m-d'),
                'end_at' => $endDate->format('Y-m-d'),
                'request_type' => $params['request_type'],
                'status' => Travel::STATUS_PENDING,
            ]);

            if(!$travel) {
                throw new WanderException(__('app.no_posible_process_request_try'));
            }

            $contacts = $request->get('contacts', []);

            if( !empty($contacts) && \is_array($contacts) ) {
                $createContacts = array_map(function($contact){
                    
                    $cUser = new \stdClass();
                    $cUser->id = null;
                    $cUser->name = $contact['name'];
                    $cUser->place_name = $contact['place_name'];
                    if($appUser = AppUser::find($contact['id'])) {
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

    /**
     * Get user's travel
     */
    public function getUserTravel(Request $request, $travel_id) {
        try {
            $user = auth($this->guard)->user();
            $modelUser = AppUser::with(['travels' => function($relationTravel){
                $relationTravel->with('activeAlbums.activePhotos');
                $relationTravel->with('host');
                $relationTravel->with('contacts');
            }])->find($user->id);
            return sendResponse($modelUser->travels->where('id',$travel_id)->first());
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


    /**
     * Get user's travels
     */
    public function getUserFinishedTravels(Request $request) {
        try {
            $user = auth($this->guard)->user();
            $modelUser = AppUser::with(['finishedTravels' => function($relationTravel){
                $relationTravel->with('activeAlbums');
                $relationTravel->with('host');
                $relationTravel->with('contacts');
            }])->find($user->id);
            return sendResponse($modelUser->finishedTravels);
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

    /**
     * Get user's travels
     */
    public function getUserScheduleTravels(Request $request) {
        try {
            $user = auth($this->guard)->user();
            
            return sendResponse($user->scheduleTravelsWithExtra());
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

    /**
     * Get user's requests travels
     */
    public function getUserRequestsTravels(Request $request) {
        try {
            $user = auth($this->guard)->user();
            return sendResponse($user->requestsTravelsWithExtra());
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
    


    /**
     * Create an album
     */
    public function createAlbum(Request $request, $travel_id) {
        try {

            DB::beginTransaction();
            
            $params = $request->only([
                'name',
                'photos',
            ]);

            $rules = [
                'name' => 'required|max:40',
                'photos' => 'required',
                'photos.*' => 'image|mimes:jpeg,png,jpg|max:'.setting('admin.file_size_limit', 2048),
            ] ;

            $validator = Validator::make($params, $rules);

            if ($validator->fails()) {
                return sendResponse(null,$validator->messages(),false);
            }


            $user = auth($this->guard)->user();

            $travel = $user->travels()->find($travel_id);

            if (!$travel) {
                DB::rollback();
                return sendResponse(null,['travel' => __('app.travel_not_selected')],false);
            }

            $listPhotos = $request->file('photos');

            $album = $travel->albums()->create([
                'name' => trim($params['name']),
                'status' => Album::STATUS_ACCEPTED,
            ]);

            $disk = config('voyager.storage.disk');
            $dt = Carbon::now('UTC')->format('FY');
            foreach($listPhotos as $uploadFilePhoto) {
                $path = $uploadFilePhoto->store("photos/album{$album->id}/$dt", [
                    'disk' => $disk,
                    //'visibility' => 'public',
                ]);
                $album->photos()->create([
                    'path' => $path,
                    'disk' => $disk,
                ]);
            }

            $travel->status = Travel::STATUS_FINISHED;

            if(!$travel->save()) {
                throw new WanderException(__('app.something_wrong_saving_album'));
            }
            
            DB::commit();
            $reAlbum = Album::with('activePhotos')->find($album->id);
            return sendResponse($reAlbum);
        } catch (QueryException $qe) {
            DB::rollback();
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            DB::rollback();
            return sendResponse(null, __('app.data_not_found'), false, $notFoundE);
        } catch (WanderException $we) {
            DB::rollback();
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            DB::rollback();
            return sendResponse(null, __('app.something_was_wrong'), false, $e);
        }
    }

    /**
     * Update an album
     */
    public function updateAlbum(Request $request, $travel_id, $album_id) {
        try {

            DB::beginTransaction();
            
            $params = $request->only([
                'name',
                'photos',
            ]);

            $rules = [
                'name' => 'max:40',
                'photos.*' => 'image|mimes:jpeg,png,jpg|max:'.setting('admin.file_size_limit', 2048),
            ] ;

            $validator = Validator::make($params, $rules);

            if ($validator->fails()) {
                return sendResponse(null,$validator->messages(),false);
            }

            $listPhotos = $request->file('photos');


            $user = auth($this->guard)->user();
            $travel = $user->travels()->find($travel_id);


            if (!$travel) {
                DB::rollback();
                return sendResponse(null,['travel' => __('app.travel_not_selected')],false);
            }

            $album = $travel->albums()->find($album_id);

            if (!$album) {
                throw new WanderException( __('app.album_not_found') );
            }

            if (!empty($params['name'])) {
                $album->name = trim($params['name']);

                if(!$album->save()) {
                    throw new WanderException(__('app.something_wrong_saving_album_name'));
                }
            }

            if( !empty($listPhotos) ) {
                $disk = config('voyager.storage.disk');
                $dt = Carbon::now('UTC')->format('FY');
                foreach($listPhotos as $uploadFilePhoto) {
                    $path = $uploadFilePhoto->store("photos/album{$album->id}/$dt", [
                        'disk' => $disk,
                        //'visibility' => 'public',
                    ]);
                    $album->photos()->create([
                        'path' => $path,
                        'disk' => $disk,
                    ]);
                } 
            }
            
            DB::commit();

            $reAlbum = Album::with('activePhotos')->find($album->id);
            return sendResponse($reAlbum);
        } catch (QueryException $qe) {
            DB::rollback();
            return sendResponse(null, __('app.database_query_exception'), false, $qe);
        } catch (ModelNotFoundException $notFoundE) {
            DB::rollback();
            return sendResponse(null, __('app.data_not_found'), false, $notFoundE);
        } catch (WanderException $we) {
            DB::rollback();
            return sendResponse(null, $we->getMessage(), false, $we);
        } catch (\Exception $e) {
            DB::rollback();
            return sendResponse(null, __('app.something_was_wrong'), false, $e);
        }
    }

    /**
     * Delete album's photos
     */
    public function deleteAlbumPhotos(Request $request, $travel_id, $album_id) {
        try {

            $params = $request->only([
                'photos',
            ]);

            $rules = [
                'photos' => 'required',
                'photos.*' => 'numeric',
            ] ;

            $validator = Validator::make($params, $rules);

            if ($validator->fails()) {
                return sendResponse(null,$validator->messages(),false);
            }

            $listPhotosIds = $params['photos'];


            $user = auth($this->guard)->user();
            $travel = $user->travels()->find($travel_id);


            if (!$travel) {
                throw new WanderException( __('app.travel_not_found') );
            }

            $album = $travel->albums()->find($album_id);

            if (!$album) {
                throw new WanderException( __('app.album_not_found') );
            }

            foreach($listPhotosIds as $photo_id) {
                $photo = Photo::find($photo_id);
                if($photo) {
                    $photo->removePermanently();
                }
            }

            return sendResponse($album->activePhotos()->get());
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

    /**
     * Get album's photos
     */
    public function getAlbumPhotos(Request $request, $travel_id, $album_id) {
        try {

            $user = auth($this->guard)->user();
            $travel = $user->travels()->find($travel_id);


            if (!$travel) {
                throw new WanderException( __('app.travel_not_found') );
            }

            $album = $travel->albums()->find($album_id);

            if (!$album) {
                throw new WanderException( __('app.album_not_found') );
            }

            return sendResponse($album->activePhotos()->get());
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

    /**
     * Return counters for notifications section
     */
    public function countersNotifications(Request $request) {
        try {
            $user = auth($this->guard)->user();

            return sendResponse(array(
                'finished_travels' => $user->finishedTravels()->count(),
                'schedule_travels' => $user->scheduleTravels()->count(),
                'requests_travels' => $user->requestsTravels()->count(),
                'recommendations' => $user->visitRecommendations()->count(),
            ));

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


    /**
     * Change the travel's status
     */
    public function changeTravelStatus(Request $request, $travel_id) {
        try {

            $params = $request->only([
                'travel_status',
                'func_travels',
            ]);
            
            $rules = [
                'travel_status' => [
                    'required',
                    Rule::in([
                        Travel::STATUS_ACCEPTED,
                        Travel::STATUS_CANCELLED,
                        Travel::STATUS_PENDING,
                        Travel::STATUS_REJECTED,
                        Travel::STATUS_REMOVED,
                        Travel::STATUS_FINISHED,
                    ]),
                ],
                'func_travels' => [
                    'required',
                    Rule::in([
                        'scheduleTravelsWithExtra',
                        'requestsTravelsWithExtra',
                    ]),
                ],
            ] ;

            $validator = Validator::make($params, $rules);

            if ($validator->fails()) {
                return sendResponse(null,$validator->messages(),false);
            }
            $user = auth($this->guard)->user();

            $travel = $user->travels()->find($travel_id);

            $newStatus = $params['travel_status'];

            if(!$travel) {
                
                $travel = $user->hostTravels()->find($travel_id);
                if($travel) {
                    switch ($newStatus) {
                        case Travel::STATUS_ACCEPTED:
                            $travel->notifyAcceptHostRequest();
                            break;
                        case Travel::STATUS_REJECTED:
                            $travel->notifyRejectHostRequest();
                            break;
                        
                        default:
                            throw new WanderException( __('app.travel_not_found') );
                            break;
                    }
                } else {
                    throw new WanderException( __('app.travel_not_found') );
                }
            }

            $travel->status = $newStatus;

            if(!$travel->save()) {
                throw new WanderException(__('app.something_wrong_changing_travel_status'));
            }
            
            return sendResponse(call_user_func(array($user, $params['func_travels'])));
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

    /**
     * Change the travel's range dates
     */
    public function changeTravelDates(Request $request, $travel_id) {
        try {

            $params = $request->only([
                'start',
                'end',
            ]);

            $rules = [
                'start' => [
                    function ($attribute, $value, $fail) {
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}/',$value)) {
                            $fail(__('app.start_date_travel_not_valid'));
                            return;
                        }
                    }
                ],
                'end' => [
                    function ($attribute, $value, $fail) {
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}/',$value)) {
                            $fail(__('app.end_date_travel_not_valid'));
                            return;
                        }
                    }
                ],
            ] ;

            $validator = Validator::make($params, $rules);

            if ($validator->fails()) {
                return sendResponse(null,$validator->messages(),false);
            }

            $startDate = Carbon::createFromFormat('Y-m-d',$params['start']);
            $endDate = Carbon::createFromFormat('Y-m-d',$params['end']);

            $now = Carbon::now('UTC');

            if($now->diffInDays($startDate, false) <= 0) {
                throw new WanderException(__('app.dates_range_not_valid'));
            }
            if($startDate->diffInDays($endDate, false) <= 0) {
                throw new WanderException(__('app.dates_range_not_valid'));
            }
            
            $user = auth($this->guard)->user();

            $travel = $user->scheduleTravels()->find($travel_id);

            if(!$travel) {
                throw new WanderException( __('app.travel_not_found') );
            }

            $travel->start_at = $startDate->format('Y-m-d');
            $travel->end_at = $endDate->format('Y-m-d');

            if(!$travel->save()) {
                throw new WanderException(__('app.something_wrong_changing_travel_dates'));
            }

            return sendResponse($user->scheduleTravelsWithExtra());
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


    /**
     * Create a the travel recommendation
     */
    public function createRecommendation(Request $request, $travel_id) {
        try {

            $user = auth($this->guard)->user();

            $travel = $user->travels()->find($travel_id);

            if(!$travel) {
                throw new WanderException( __('app.travel_not_found') );
            }

            $recommendationsArr = [];

            $friendsIds = $request->get('friends_ids', []);
            $foundR  = null;
            if( is_array($friendsIds) ) {
                foreach($friendsIds as $friend_id) {
                    $foundR = Recommendation::where('user_id', $user->id)
                                    ->where('invited_id', $friend_id)
                                    ->where('travel_id', $travel->id)
                                    ->get()->first();
                    
                    if(!$foundR) {
                        $recommendationsArr[] = [
                            'user_id' => $user->id,
                            'invited_id' => $friend_id,
                        ];
                    }
                }
            }

            $recommendations = $travel->recommendations()->createMany($recommendationsArr);

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
