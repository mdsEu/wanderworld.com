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
                'end' => [
                    function ($attribute, $value, $fail) {
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}/',$value)) {
                            $fail(__('xx:The dates for your travel are not valid.'));
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

            $host = $user->activeFriendsLevel( 2 )->find($host_id);

            if(!$host) {
                throw new WanderException(__('xx:In this moment this friend is not accepting host or guide requests 1'));
            }
            
            $isMyFriend = $user->isMyFriend($host);
            if($isMyFriend && $host->pivot->status === AppUser::FRIEND_STATUS_BLOCKED_REQUESTS) {
                throw new WanderException(__('xx:In this moment this friend is not accepting host or guide requests 2'));
            }

            if(!$isMyFriend) {
                /**
                 * To DO
                 */
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
                throw new WanderException(__('xx:It was not posible to process the request. Try again.'));
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
                'name' => 'required|max:20',
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
                throw new WanderException( __('xx:Travel not found') );
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
            
            DB::commit();

            return sendResponse($album->photos()->get());
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
                'name' => 'max:20',
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
                throw new WanderException( __('xx:Travel not found') );
            }

            $album = $user->albums()->find($album_id);

            if (!$album) {
                throw new WanderException( __('xx:Album not found') );
            }

            $album->name = trim($params['name']);

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

            return sendResponse($album->photos()->get());
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
                throw new WanderException( __('xx:Travel not found') );
            }

            $album = $travel->albums()->find($album_id);

            if (!$album) {
                throw new WanderException( __('xx:Album not found') );
            }

            foreach($listPhotosIds as $photo_id) {
                $photo = Photo::find($photo_id);
                if($photo) {
                    $photo->removePermanently();
                }
            }

            return sendResponse($album->photos()->get());
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
