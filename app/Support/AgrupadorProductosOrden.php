<?php

namespace App\Support;

use Illuminate\Support\Collection;

class AgrupadorProductosOrden
{
    /**
     * Agrupa las líneas de una orden (una por canje) por producto.
     * Suma cantidades e importes y conserva los id_validacion de cada línea.
     */
    public static function agrupar(iterable $productos): Collection
    {
        return collect($productos)
            ->map(fn($p) => (array) $p)
            ->groupBy(fn($p) => (string) ($p['id_producto'] ?? ''))
            ->map(function (Collection $lineas) {
                $primera  = $lineas->first();
                $cantidad = (int) $lineas->sum(fn($p) => (int) ($p['cantidad_producto'] ?? 0));
                $subtotal = (float) $lineas->sum(fn($p) => (float) ($p['subtotal'] ?? 0));
                $iva      = (float) $lineas->sum(fn($p) => (float) ($p['iva'] ?? 0));

                $estatus = $lineas->map(fn($p) => (int) ($p['estatus_proveedor'] ?? 0))->unique();

                return array_merge($primera, [
                    'cantidad_producto' => $cantidad,
                    'precio_unitario'   => $cantidad > 0
                        ? round($subtotal / $cantidad, 2)
                        : (float) ($primera['precio_unitario'] ?? 0),
                    'subtotal'          => $subtotal,
                    'iva'               => $iva,
                    'importe_total'     => $subtotal + $iva,
                    'estatus_proveedor' => $estatus->count() === 1 ? $estatus->first() : 0,
                    'ids_validacion'    => $lineas->pluck('id_validacion')->filter()->values()->all(),
                    'total_canjes'      => $lineas->pluck('id_canje')->filter()->unique()->count(),
                ]);
            })
            ->values();
    }
}
