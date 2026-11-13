<?php

namespace App\Services\ReporteSemanal;

use Illuminate\Support\Collection;

/**
 * Agrupa las filas del reporte
 */
class AgrupadorProveedores
{
    public const SIN_PROVEEDOR = 'Sin proveedor';

    public function __construct(private readonly TotalesReporteSemanal $totales) {}

    /**
     * Semanas con registros, cada una con sus proveedores.
     *
     * @return array<int, array{semana: array, totales: array, proveedores: array}>
     */
    public function porSemana(array $reporte): array
    {
        return collect($reporte['semanas'])
            ->filter(fn(array $s) => collect($s['registros'])->isNotEmpty())
            ->map(fn(array $s) => [
                'semana' => collect($s)->except(['registros', 'totales'])->all(),
                'totales' => $s['totales'],
                'proveedores' => $this->proveedores(collect($s['registros'])),
            ])
            ->values()
            ->all();
    }

    /**
     * Bloques semana + proveedor en una sola lista
     *
     * @return array<int, array{semana: array, nombre_proveedor: string, registros: array, totales: array}>
     */
    public function agrupar(array $reporte): array
    {
        return collect($this->porSemana($reporte))
            ->flatMap(fn(array $s) => array_map(
                fn(array $p) => ['semana' => $s['semana']] + $p,
                $s['proveedores']
            ))
            ->all();
    }

    private function proveedores(Collection $registros): array
    {
        return $registros
            ->groupBy(fn(array $f) => $f['id_proveedor'] !== null
                ? 'id-' . $f['id_proveedor']
                : 'n-' . ($f['nombre_proveedor'] ?? ''))
            ->map(fn(Collection $filas) => [
                'nombre_proveedor' => $filas->first()['nombre_proveedor'] ?: self::SIN_PROVEEDOR,
                'registros' => $filas->values()->all(),
                'totales' => $this->totales->calcular($filas),
            ])
            ->sortBy('nombre_proveedor', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
