<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class AccountDeactivate extends Mailable
{
    use Queueable, SerializesModels;

    public $user_reason;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct( $user_reason )
    {
        $this->user_reason = $user_reason;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.deactivateAccount');
    }
}
