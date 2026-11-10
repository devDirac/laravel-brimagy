<?php

namespace App\Http\Controllers\API\Concerns;

use App\Exceptions\DatosInvalidosException;
use App\Exceptions\RecursoNoEncontradoException;
use App\Exceptions\ReglaNegocioException;

/**
 * Convierte las excepciones de los servicios en respuestas con el formato de sendError,
 * para que cada método del controller no repita el mismo try/catch.
 */
trait RespondeConServicios
{
    /**
     * @param callable $accion       Regresa la respuesta exitosa
     * @param string   $mensajeError Mensaje para errores inesperados (500)
     */
    protected function responder(callable $accion, string $mensajeError)
    {
        try {
            return $accion();
        } catch (DatosInvalidosException $e) {
            return $this->sendError($e->getMessage(), $e->errores());
        } catch (RecursoNoEncontradoException $e) {
            return $this->sendError($e->getMessage(), 'error', 404);
        } catch (ReglaNegocioException $e) {
            return $this->sendError($e->getMessage(), 'error', 422);
        } catch (\Throwable $th) {
            report($th);
            return $this->sendError($mensajeError, $th->getMessage(), 500);
        }
    }
}
