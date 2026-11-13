<?php

namespace App\Services\ReporteSemanal;

use App\Models\Periodo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Consultas de dc_periodos para el reporte general (cada periodo trae el nombre de su plataforma). */
class PeriodosReporte
{
    /** Todos los periodos, del más reciente al más antiguo. */
    public function listar(): Collection
    {
        return $this->consulta()
            ->orderByDesc('dc_periodos.fecha_inicio')
            ->orderByDesc('dc_periodos.id')
            ->get()
            ->map(fn(Periodo $p) => [
                'id' => (int) $p->id,
                'id_plataforma' => (int) $p->id_plataforma,
                'plataforma' => $p->plataforma,
                'fecha_inicio' => $p->fecha_inicio,
                'fecha_fin' => $p->fecha_fin,
                'activo' => (int) $p->activo,
            ]);
    }

    public function porId(int $id): ?Periodo
    {
        return $this->consulta()->where('dc_periodos.id', $id)->first();
    }

    public function activoMasReciente(): ?Periodo
    {
        return $this->consulta()
            ->where('dc_periodos.activo', 1)
            ->orderByDesc('dc_periodos.fecha_inicio')
            ->orderByDesc('dc_periodos.id')
            ->first();
    }

    private function consulta(): Builder
    {
        return Periodo::query()
            ->leftJoin('dc_plataformas', 'dc_plataformas.id', '=', 'dc_periodos.id_plataforma')
            ->select('dc_periodos.*', 'dc_plataformas.nombre as plataforma');
    }
}
