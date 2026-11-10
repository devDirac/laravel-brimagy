<?php

namespace App\Exceptions;

use RuntimeException;

/** La operación no se permite por una regla del negocio (ej. un color repetido en el producto). */
class ReglaNegocioException extends RuntimeException
{
}
