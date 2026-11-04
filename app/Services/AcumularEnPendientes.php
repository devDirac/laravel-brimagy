<?php

namespace App\Services;

use App\Contracts\EstrategiaCotizacionMultiple;
use Illuminate\Support\Facades\DB;

class AcumularEnPendientes implements EstrategiaCotizacionMultiple
{
    public function __construct(private DuplicadorValidacionCanje $duplicador)
    {
    }

    public function ejecutar(array $asignaciones, int $idUsuario): ResultadoCotizacionMultiple
    {
        return DB::transaction(function () use ($asignaciones) {
            $nuevas = $this->duplicador->duplicar($asignaciones)
                ->filter(fn($v) => $v->wasRecentlyCreated)
                ->count();

            return new ResultadoCotizacionMultiple(
                'acumular',
                $nuevas,
                0,
                "Se agregaron {$nuevas} registro(s) a los pendientes de los proveedores seleccionados."
            );
        });
    }
}