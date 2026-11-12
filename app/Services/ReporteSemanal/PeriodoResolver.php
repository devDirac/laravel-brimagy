<?php

namespace App\Services\ReporteSemanal;

use App\Exceptions\RecursoNoEncontradoException;
use App\Models\Periodo;
use App\Models\Plataformas;

/**
 * Decide qué periodo usa el reporte
 */
class PeriodoResolver
{
    public function resolver(FiltrosReporteSemanal $filtros): PeriodoSemanal
    {
        $idPlataforma = Plataformas::where('nombre', $filtros->nombrePlataforma())->value('id');

        if (!$idPlataforma) {
            throw new RecursoNoEncontradoException("La plataforma {$filtros->plataforma} no existe");
        }

        $periodo = Periodo::where('id_plataforma', $idPlataforma)
            ->when(
                $filtros->idPeriodo,
                fn($q) => $q->where('id', $filtros->idPeriodo),
                fn($q) => $q->where('activo', 1)->latest('id')
            )
            ->first();

        if (!$periodo) {
            throw new RecursoNoEncontradoException(
                $filtros->idPeriodo
                    ? 'El periodo no existe para esta plataforma'
                    : 'La plataforma no tiene un periodo activo registrado'
            );
        }

        return PeriodoSemanal::desdeModelo($periodo);
    }
}
