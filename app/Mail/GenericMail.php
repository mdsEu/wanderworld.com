<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\AppUser;
use Mail;


class GenericMail extends Mailable {

    use Queueable, SerializesModels;

    public $title;
    public $description;
    public $button;
    public $image;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($title, $description, $button = null, $image = null)
    {
        $this->title   = $title;
        $this->description = $description;
        $this->button = $button;
        $this->image = $image ? $image : \Illuminate\Support\Facades\Storage::disk(config('voyager.storage.disk'))->url('mails/bus-welcome.png');
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build() {

        return $this->view('mails.generic');
    }

}
