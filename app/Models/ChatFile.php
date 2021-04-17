<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatFile extends Model
{
    use HasFactory, SoftDeletes;


    protected $table = 'chat_files';


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
}
