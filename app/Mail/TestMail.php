<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data; // optional dynamic content
    }

    public function build()
    {
        return $this->subject('Test SMTP Email')->text('emails.test_plain');
    }
}
