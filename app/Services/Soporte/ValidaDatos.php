<?php

namespace App\Services\Soporte;

use App\Exceptions\DatosInvalidosException;
use Illuminate\Support\Facades\Validator;

trait ValidaDatos
{
    /** Lanza DatosInvalidosException si $datos no cumple $reglas. */
    protected function validar(array $datos, array $reglas, string $mensaje = 'El formato de datos no es válido.'): void
    {
        $validador = Validator::make($datos, $reglas);
        if ($validador->fails()) {
            throw new DatosInvalidosException($validador->errors(), $mensaje);
        }
    }
}
