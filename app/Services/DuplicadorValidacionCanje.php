<?php

namespace App\Services;

use App\Models\ValidacionCanje;
use Illuminate\Support\Collection;

class DuplicadorValidacionCanje
{
    public function __construct(private CierreCanjesService $cierre)
    {
    }

    /**
     * @param array<int, int[]> $asignaciones [id_validacion => [id_proveedor, ...]]
     */
    public function duplicar(array $asignaciones): Collection
    {
        $origenes = ValidacionCanje::whereIn('id', array_keys($asignaciones))->get()->keyBy('id');
        $resultado = collect();

        foreach ($asignaciones as $idValidacion => $proveedores) {
            $origen = $origenes->get($idValidacion);

            // Se puede cotizar mientras el producto del canje no esté ejecutado,
            // aunque el registro ya esté en una orden (disponible o no disponible)
            if (
                !$origen
                || !empty($origen->orden_ejecutada)
                || $this->cierre->grupoEjecutado($origen->id_canje, $origen->id_producto)
            ) {
                continue;
            }

            foreach (array_unique(array_map('intval', $proveedores)) as $idProveedor) {
                if ((int) $origen->id_proveedor === $idProveedor) {
                    if (empty($origen->no_orden)) {
                        $resultado->push($origen);
                    }
                    continue;
                }

                $existente = ValidacionCanje::where('id_canje', $origen->id_canje)
                    ->where('id_producto', $origen->id_producto)
                    ->where('id_proveedor', $idProveedor)
                    ->where(fn($q) => $q->whereNull('no_orden')->orWhere('no_orden', ''))
                    ->first();

                if ($existente) {
                    $resultado->push($existente);
                    continue;
                }

                $copia = $origen->replicate(['no_orden', 'orden_ejecutada', 'fecha_ejecucion']);
                $copia->id_proveedor = $idProveedor;
                $copia->no_orden = null;
                $copia->id_validacion_origen = $origen->id_validacion_origen ?? $origen->id;
                $copia->save();

                $resultado->push($copia);
            }
        }

        return $resultado;
    }
}