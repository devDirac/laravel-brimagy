<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IdentidadValidada extends Mailable
{
    use Queueable, SerializesModels;

    public $canje;
    public $codigo;
    public $urlval;

    public function __construct($canje, $codigo, $urlval)
    {
        $this->canje = $canje;
        $this->codigo = $codigo;
        $this->urlval = $urlval;
    }

    public function build()
    {
        return $this->subject('Identidad validada correctamente - Folio #' . $this->canje->folio)
            ->view('emails.identidad-validada');
    }
}
