<?php

namespace App\Contracts;

use App\Services\ResultadoCotizacionMultiple;

interface EstrategiaCotizacionMultiple
{
    /**
     * @param array<int, int[]> $asignaciones [id_validacion => [id_proveedor, ...]]
     */
    public function ejecutar(array $asignaciones, int $idUsuario): ResultadoCotizacionMultiple;
}