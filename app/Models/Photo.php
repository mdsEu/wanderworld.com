<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;

use Carbon\Carbon;
use App\Exceptions\WanderException;



class Photo extends Model
{
    use HasFactory, SoftDeletes;


    const STATUS_ACCEPTED    = '1';
    const STATUS_REPORTED    = '2';
    const STATUS_BLOCKED     = '3';

    protected $guarded = [];
    
    protected $table = 'photos';


    /**
     * 
     */
    public function show() {
        $disk = config('voyager.storage.disk');
        if($disk !== $this->disk) {
            /**
             * To DO
             */
        }
        return Storage::disk(config('voyager.storage.disk'))->response($this->path);
    }

    /**
     * 
     */
    public function storageUrl() {
        return Storage::disk(config('voyager.storage.disk'))->url($this->path);
    }

    /**
     * 
     */
    public function removePermanently() {
        if($this->forceDelete()) {
            Storage::disk(config('voyager.storage.disk'))->delete($this->path);
            return true;
        }
        return false;
    }

    /**
     * ToArray function
     */
    public function toArray() {
        $request = request();
        $myAppends = [
        ];
        if( 
            $request->is('api/services/v1/friends/*/finished-travels')
        ) {
            $temp = array_merge($this->attributesToArray(), $this->relationsToArray(), $myAppends);
            $re = [];
            foreach($temp as $key=>$item) {
                if(!in_array($key,['id','status'])) {
                    continue;
                }
                $re[$key] = $item;
            }
            return $re;
        }
        
        return array_merge($this->attributesToArray(), $this->relationsToArray(), $myAppends);
    }


}
