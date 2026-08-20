<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EnvioOrdenCompra extends Mailable
{
    use Queueable, SerializesModels;

    public $productosData;
    public $urlval;

    public function __construct($productosData, $urlval)
    {
        $this->productosData = $productosData;
        $this->urlval = $urlval;
    }

    public function build()
    {
        return $this->subject('Orden de compra generada')
            ->view('emails.orden-compra');
    }
}