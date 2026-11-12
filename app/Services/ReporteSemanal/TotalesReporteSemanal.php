<?php

namespace App\Services\ReporteSemanal;

use Illuminate\Support\Collection;

class TotalesReporteSemanal
{
    /** @param Collection<int, array> $filas */
    public function calcular(Collection $filas): array
    {
        return [
            'registros' => $filas->count(),
            'cantidad_gestionada' => (int) $filas->sum('cantidad_gestionada'),
            'entrega' => (int) $filas->sum('entrega'),
            'faltante' => (int) $filas->sum('faltante'),
            'total' => round((float) $filas->sum('total'), 2),
        ];
    }
}
