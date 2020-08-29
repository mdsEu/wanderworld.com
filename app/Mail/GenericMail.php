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
    public $extraContent;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($title, $description, $button = null, $extraContent = null)
    {
        $this->title   = $title;
        $this->description = $description;
        $this->button = $button;
        $this->extraContent = $extraContent;
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
