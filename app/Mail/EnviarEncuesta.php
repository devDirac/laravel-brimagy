<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EnviarEncuesta extends Mailable
{
    use Queueable, SerializesModels;

    public $orden;
    public $urlval;

    public function __construct($orden, $urlval)
    {
        $this->orden = $orden;
        $this->urlval = $urlval;
    }

    public function build()
    {
        return $this->subject('Nueva encuesta recibida')
            ->view('emails.envio-encuesta');
    }
}
