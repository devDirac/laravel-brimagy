<?php

namespace App\Services\ReporteSemanal;

use App\Contracts\RegistrosAlmacenPeriodo;
use App\Models\Plataformas;
use App\Services\FolioCanjeResolver;
use Illuminate\Support\Collection;

/**
 * Reporte de almacén del periodo de una plataforma
 *
 * Solo orquesta: cada paso (periodo, consulta, folio, forma de la fila, totales)
 * vive en su propia clase.
 */
class ReporteSemanalAlmacenService
{
    public function __construct(
        private readonly PeriodoResolver $periodos,
        private readonly RegistrosAlmacenPeriodo $registros,
        private readonly FolioCanjeResolver $folios,
        private readonly FilaReporteSemanal $fila,
        private readonly TotalesReporteSemanal $totales,
    ) {}

    public function generar(FiltrosReporteSemanal $filtros): array
    {
        $periodo = $this->periodos->resolver($filtros);

        $filasPorSemana = $this->conFolio($this->registros->obtener($periodo))
            ->map(function ($registro) use ($periodo) {
                $semana = $periodo->numeroSemana($registro->fecha_referencia);
                return $this->fila->mapear($registro, $semana);
            })
            ->filter(fn(array $f) => $f['semana'] !== null)
            ->groupBy('semana');

        $semanas = collect($periodo->semanas())
            ->when($filtros->semana, fn(Collection $s) => $s->where('numero', $filtros->semana))
            ->map(function (Semana $semana) use ($filasPorSemana) {
                $filas = $filasPorSemana->get($semana->numero, collect())->values();

                return $semana->toArray() + [
                    'registros' => $filas,
                    'totales' => $this->totales->calcular($filas),
                ];
            })
            ->values();

        return [
            'plataforma' => $filtros->nombrePlataforma(),
            'periodo' => $periodo->toArray(),
            'semanas' => $semanas,
            'totales' => $this->totales->calcular($semanas->pluck('registros')->flatten(1)),
        ];
    }

    /** Agrega el folio del canje */
    private function conFolio(Collection $registros): Collection
    {
        if ($registros->isEmpty()) {
            return $registros;
        }

        return $this->folios->enriquecer($registros, Plataformas::pluck('nombre', 'id'));
    }
}
