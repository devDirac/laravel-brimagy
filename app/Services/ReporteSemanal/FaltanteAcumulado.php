<?php

namespace App\Services\ReporteSemanal;

use Illuminate\Support\Collection;

/**
 * Faltante acumulado de un pedido recibido en partes con precios distintos.
 * Recorre los renglones de cada canje + producto en el orden en que se registraron y
 * agrega a cada uno:
 *  - faltante_acumulado: lo que faltaba después de esa recepción (pedido - recibido hasta ahí)
 *  - es_ultimo: si es el último renglón del producto; solo ese cuenta en los totales,
 *    porque su faltante es el estado real del pedido.
 */
class FaltanteAcumulado
{
    public function aplicar(Collection $registros): Collection
    {
        $registros
            ->groupBy(fn(object $r) => "{$r->id_canje}-{$r->id_producto}")
            ->each(function (Collection $lineas) {
                $ordenadas = $lineas->sortBy('id_producto_almacen')->values();
                $ultimo = $ordenadas->count() - 1;
                $recibido = 0;

                foreach ($ordenadas as $i => $registro) {
                    $recibido += (int) $registro->cantidad_entregada;
                    $registro->faltante_acumulado = max((int) $registro->cantidad_gestionada - $recibido, 0);
                    $registro->es_ultimo = $i === $ultimo;
                }
            });

        return $registros;
    }
}
