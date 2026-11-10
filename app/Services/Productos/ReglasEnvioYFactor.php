<?php

namespace App\Services\Productos;

/**
 * De dónde salen envio_extra y valor_factor de un producto.
 *
 * Reglas:
 *   1. Lo que trae el formulario (qué cuenta como "trae valor" depende del origen).
 *   2. Edición: lo que el producto ya tiene guardado.
 *   3. Las VariablesGlobales de la plataforma.
 *   4. Los valores por defecto de la plataforma (envio_extra 0, valor_factor 15).
 */
class ReglasEnvioYFactor
{
    const COLUMNAS_VARIABLES = [
        'envio_extra' => 'envio_extra',
        'valor_factor' => 'factor',
    ];

    /** Excel: una celda vacía o en 0 cuenta como no capturada. */
    public function paraExcel(array $datos, $variables, array $porDefecto): array
    {
        return $this->resolver(function (string $campo) use ($datos) {
            $valor = $datos[$campo] ?? null;

            return is_numeric($valor) && (float) $valor != 0 ? $valor : null;
        }, $variables, $porDefecto);
    }

    /** Registro individual: solo vacío cuenta como no capturado */
    public function paraIndividual(array $datos, $variables, array $porDefecto): array
    {
        return $this->resolver(function (string $campo) use ($datos) {
            return $this->tieneValor($datos[$campo] ?? null) ? $datos[$campo] : null;
        }, $variables, $porDefecto);
    }

    /**
     * Edición: lo recibido se respeta aunque sea 0. Si no llega, se conserva lo que el producto
     * ya tiene registrado; solo si no tiene nada se va a las variables y luego al valor por defecto.
     */
    public function paraEdicion(array $datos, $producto, $variables, array $porDefecto): array
    {
        return $this->resolver(function (string $campo) use ($datos, $producto) {
            if ($this->tieneValor($datos[$campo] ?? null)) {
                return $datos[$campo];
            }

            return $this->tieneValor($producto->$campo) ? $producto->$campo : null;
        }, $variables, $porDefecto);
    }

    private function resolver(callable $valorCapturado, $variables, array $porDefecto): array
    {
        $resultado = [];
        foreach (self::COLUMNAS_VARIABLES as $campo => $columnaVariables) {
            $valor = $valorCapturado($campo);

            if ($valor === null && $variables && $this->tieneValor($variables->$columnaVariables)) {
                $valor = $variables->$columnaVariables;
            }

            $resultado[$campo] = $valor ?? $porDefecto[$campo];
        }

        return $resultado;
    }

    /** null, '' y 'null' no son valor; 0 sí lo es. */
    private function tieneValor($valor): bool
    {
        return !in_array($valor, [null, '', 'null', 'undefined'], true) && trim((string) $valor) !== '';
    }
}
