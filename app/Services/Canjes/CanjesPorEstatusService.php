<?php

namespace App\Services\Canjes;

use Illuminate\Support\Collection;

/**
 * Agrupa los canjes por estatus de validación: conteo para el contador y
 * filas filtradas para la tabla del modal. Un canje = un id, sin importar cuántos productos tenga.
 */
class CanjesPorEstatusService
{
    public function __construct(private readonly ConsultaCanjesService $consulta)
    {
    }

    /** @return array{total:int, sin_validar:int, en_proceso:int, validados:int} */
    public function resumen(FiltrosCanjes $filtros): array
    {
        $grupos = $this->estatusPorCanje($this->consulta->consultar($filtros))
            ->countBy(fn(?string $estatus) => EstatusCanje::desdeValidacion($estatus)->value);

        return [
            'total' => $grupos->sum(),
            'sin_validar' => $grupos->get(EstatusCanje::SinValidar->value, 0),
            'en_proceso' => $grupos->get(EstatusCanje::EnProceso->value, 0),
            'validados' => $grupos->get(EstatusCanje::Validados->value, 0),
        ];
    }

    /** Filas (una por producto, mismo formato que getCanjes) de los canjes que caen en el grupo. */
    public function listar(FiltrosCanjes $filtros, EstatusCanje $estatus): Collection
    {
        $filas = $this->consulta->consultar($filtros);

        if ($estatus === EstatusCanje::Total) {
            return $filas->values();
        }

        $idsIncluidos = $this->estatusPorCanje($filas)
            ->filter(fn(?string $estatusValidacion) => $estatus->incluye($estatusValidacion))
            ->keys()
            ->flip();

        return $filas
            ->filter(fn($fila) => $idsIncluidos->has($fila->id))
            ->values();
    }

    /** [id_canje => estatus de validación] tomando la primera fila de cada canje. */
    private function estatusPorCanje(Collection $filas): Collection
    {
        return $filas
            ->groupBy('id')
            ->map(fn(Collection $grupo) => $grupo->first()->estado_validacion);
    }
}