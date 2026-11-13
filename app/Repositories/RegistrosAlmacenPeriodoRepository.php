<?php

namespace App\Repositories;

use App\Contracts\RegistrosAlmacenPeriodo;
use App\Services\ReporteSemanal\PeriodoSemanal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consulta de dc_recepcion_almacen para el reporte semanal (todas las plataformas).
 *
 * Una fila por canje + producto + precio de compra: si un pedido se recibe en partes
 * con precios distintos, sale un renglón por cada precio.
 *  - El pedido (cantidad_producto) del canje + producto se toma una sola vez
 *    (subconsulta "cp"); el primer renglón lo muestra y los demás se marcan como
 *    continuación para no duplicarlo en los totales.
 *  - entrega y total son de cada precio; el faltante acumulado lo calcula FaltanteAcumulado.
 *  - fecha_referencia es la del canje + producto completo, para que todos sus renglones
 *    caigan en la misma semana.
 */
class RegistrosAlmacenPeriodoRepository implements RegistrosAlmacenPeriodo
{
    public function obtener(PeriodoSemanal $periodo): Collection
    {
        return DB::query()
            ->fromSub($this->lineasPorPrecio(), 'ag')
            ->joinSub($this->lineasPorCanjeProducto(), 'cp', function ($join) {
                $join->on('cp.id_canje', '=', 'ag.id_canje')
                    ->on('cp.id_producto', '=', 'ag.id_producto');
            })
            ->join('dc_catalogo_productos as cdp', 'cdp.id', '=', 'ag.id_producto')
            ->leftJoin('dc_plataformas as pl', 'pl.id', '=', 'cdp.id_plataforma')
            ->leftJoin('dc_catalogo_proveedores as cp_prov', 'cp_prov.id', '=', DB::raw('COALESCE(ag.id_proveedor, cdp.id_proveedor)'))
            ->leftJoin('dc_orden_compra as oc', 'oc.id', '=', 'ag.id_orden_compra')
            // Lo recibido; el registro sin recibir solo si el producto aún no tiene recepciones
            ->where(fn($q) => $q->where('ag.recibido', 1)->orWhere('cp.cantidad_entregada_total', 0))
            ->whereBetween('cp.fecha_referencia', [
                $periodo->inicio->toDateString(),
                $periodo->fin->toDateString(),
            ])
            ->select(
                'ag.id_producto_almacen',
                'ag.id_canje',
                'ag.id_producto',
                'cdp.id_plataforma',
                'pl.nombre as plataforma',
                DB::raw('COALESCE(ag.id_proveedor, cdp.id_proveedor) as id_proveedor'),
                'cp_prov.nombre as nombre_proveedor',
                'cdp.nombre_producto',
                'cdp.sku',
                'cdp.marca',
                'cdp.costo_con_iva as precio',
                // Pedido y avance del canje + producto completo (se usan una sola vez)
                'cp.cantidad_gestionada',
                'cp.cantidad_entregada_total',
                DB::raw('CASE WHEN ag.id_producto_almacen = cp.id_primero THEN 0 ELSE 1 END as es_continuacion'),
                // Lo de este precio
                'ag.cantidad_entregada',
                // precio_compra de almacén tal cual; si no está, el costo con IVA del catálogo
                DB::raw('COALESCE(ag.precio_compra, cdp.costo_con_iva) as precio_unitario_compra'),
                'ag.total_compra',
                'ag.fecha_factura',
                'ag.folio_factura',
                'cp.tipo_envio',
                'cp.estatus',
                'ag.id_orden_compra',
                'oc.no_orden',
                'cp.fecha_referencia',
            )
            ->orderBy('cp.fecha_referencia')
            ->orderBy('cp_prov.nombre')
            ->orderBy('ag.id_canje')
            ->orderBy('ag.id_producto_almacen')
            ->get();
    }

    /** Un renglón por canje + producto + precio, separando lo recibido de lo no recibido. */
    private function lineasPorPrecio(): Builder
    {
        $recibido = 'CASE WHEN COALESCE(ra.cantidad_almacen, 0) > 0 THEN 1 ELSE 0 END';

        return DB::table('dc_recepcion_almacen as ra')
            ->whereNotNull('ra.id_canje')
            ->groupBy('ra.id_canje', 'ra.id_producto', DB::raw($recibido), 'ra.precio_compra')
            ->select(
                'ra.id_canje',
                'ra.id_producto',
                'ra.precio_compra',
                DB::raw("{$recibido} as recibido"),
                DB::raw('MIN(ra.id) as id_producto_almacen'),
                DB::raw('MAX(ra.id_orden_compra) as id_orden_compra'),
                DB::raw('MAX(ra.id_proveedor) as id_proveedor'),
                DB::raw('COALESCE(SUM(ra.cantidad_almacen), 0) as cantidad_entregada'),
                DB::raw('COALESCE(SUM(ra.precio_compra * ra.cantidad_almacen), 0) as total_compra'),
                DB::raw('MAX(ra.fecha_compra) as fecha_factura'),
                DB::raw("GROUP_CONCAT(DISTINCT NULLIF(ra.folio_factura, '')) as folio_factura"),
            );
    }

    /** Datos del canje + producto completo: pedido, avance total, primer registro y fecha. */
    private function lineasPorCanjeProducto(): Builder
    {
        return DB::table('dc_recepcion_almacen as ra')
            ->whereNotNull('ra.id_canje')
            ->groupBy('ra.id_canje', 'ra.id_producto')
            ->select(
                'ra.id_canje',
                'ra.id_producto',
                // Primer registro con algo recibido; si no hay, el registro original
                DB::raw('COALESCE(MIN(CASE WHEN COALESCE(ra.cantidad_almacen, 0) > 0 THEN ra.id END), MIN(ra.id)) as id_primero'),
                DB::raw('MAX(ra.cantidad_producto) as cantidad_gestionada'),
                DB::raw('COALESCE(SUM(ra.cantidad_almacen), 0) as cantidad_entregada_total'),
                DB::raw('MAX(ra.tipo_envio) as tipo_envio'),
                DB::raw('MAX(ra.estatus) as estatus'),
                DB::raw('COALESCE(MAX(ra.fecha_compra), DATE(MIN(ra.created_at))) as fecha_referencia'),
            );
    }
}
