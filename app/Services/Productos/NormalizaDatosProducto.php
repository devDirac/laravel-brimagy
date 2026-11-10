<?php

namespace App\Services\Productos;

/** Helpers para leer los datos que llegan del formulario */
trait NormalizaDatosProducto
{
    /** Ids opcionales: '', 'null' y 'undefined' cuentan como vacíos. */
    protected function idOpcional($valor)
    {
        return in_array($valor, [null, '', 'null', 'undefined'], true) ? null : $valor;
    }

    protected function relleno(array $datos, string $campo): bool
    {
        return isset($datos[$campo]) && trim((string) $datos[$campo]) !== '';
    }

    /** Compara un valor guardado contra el del formulario: números a 2 decimales, fechas por día y textos tal cual. */
    protected function mismoValor($guardado, $nuevo): bool
    {
        if (is_numeric($guardado) && is_numeric($nuevo)) {
            return round((float) $guardado, 2) === round((float) $nuevo, 2);
        }

        if ((string) $guardado === (string) $nuevo) {
            return true;
        }

        $fechaGuardada = $this->fecha($guardado);
        $fechaNueva = $this->fecha($nuevo);

        return $fechaGuardada !== null && $fechaGuardada === $fechaNueva;
    }

    private function fecha($valor): ?string
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }
        if (!is_string($valor) || !preg_match('/^\d{4}-\d{2}-\d{2}/', $valor)) {
            return null;
        }

        return substr($valor, 0, 10);
    }
}
