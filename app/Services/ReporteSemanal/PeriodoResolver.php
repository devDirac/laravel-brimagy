<?php

namespace App\Services\ReporteSemanal;

use App\Exceptions\RecursoNoEncontradoException;

/**
 * Decide qué periodo usa el reporte general: el que pide el front (id_periodo)
 * o, si no manda ninguno, el periodo activo más reciente de cualquier plataforma.
 * El periodo solo define el rango de fechas; los registros son de todas las plataformas.
 */
class PeriodoResolver
{
    public function __construct(private readonly PeriodosReporte $periodos) {}

    public function resolver(FiltrosReporteSemanal $filtros): PeriodoSemanal
    {
        $periodo = $filtros->idPeriodo
            ? $this->periodos->porId($filtros->idPeriodo)
            : $this->periodos->activoMasReciente();

        if (!$periodo) {
            throw new RecursoNoEncontradoException(
                $filtros->idPeriodo ? 'El periodo no existe' : 'No hay un periodo activo registrado'
            );
        }

        return PeriodoSemanal::desdeModelo($periodo);
    }
}
