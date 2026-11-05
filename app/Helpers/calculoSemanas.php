<?php

use Carbon\Carbon;

if (!function_exists('calcularNumSemana')) {
    function calcularNumSemana($fechaInicio, $fechaFin, $fecha): int
    {
        $inicio = Carbon::parse($fechaInicio)->startOfDay();
        $fin = Carbon::parse($fechaFin)->startOfDay();
        $objetivo = Carbon::parse($fecha)->startOfDay();

        if ($objetivo->lt($inicio) || $objetivo->gt($fin)) {
            throw new InvalidArgumentException(
                "Fuera del periodo"
            );
        }

        return intdiv($inicio->diffInDays($objetivo), 7) + 1;
    }
}
if (!function_exists('calcularNombreMes')) {
    function calcularNombreMes($fecha): string
    {
        return ucfirst(Carbon::parse($fecha)->locale('es')->translatedFormat('F'));
    }
}