<?php

namespace App\Exceptions;

use Illuminate\Support\MessageBag;
use RuntimeException;

/** Los datos del formulario no pasan la validación. El controller responde con sendError y los errores. */
class DatosInvalidosException extends RuntimeException
{
    private $errores;

    public function __construct(MessageBag $errores, string $mensaje = 'El formato de datos no es válido.')
    {
        parent::__construct($mensaje);
        $this->errores = $errores;
    }

    public function errores(): MessageBag
    {
        return $this->errores;
    }
}
