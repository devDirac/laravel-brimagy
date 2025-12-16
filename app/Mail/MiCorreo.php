<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MiCorreo extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $data;
    public $blade;

    public function __construct($subject, $data, $blade)
    {
        $this->subject = $subject;
        $this->data = $data;
        $this->blade = $blade;
    }

    public function build()
    {
        return $this->subject($this->subject)
            ->markdown($this->blade)
            ->with($this->data);
    }
}
