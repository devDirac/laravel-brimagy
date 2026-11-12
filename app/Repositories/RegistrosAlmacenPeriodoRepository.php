<?php

namespace App\Repositories;

use App\Contracts\RegistrosAlmacenPeriodo;
use App\Services\ReporteSemanal\PeriodoSemanal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consulta de dc_recepcion_almacen para el reporte semanal.
 *
 *  - cantidad_gestionada: cantidad que se mandó gestionar a almacén (cantidad_producto)
 *  - cantidad_entregada: lo que ya se recibió de esa cantidad, suma de cantidad_almacen
 *  - fecha_referencia: fecha con la que se ubica la semana = fecha de compra (factura); si aún no se compra, la fecha en que entró a almacén
 */
class RegistrosAlmacenPeriodoRepository implements RegistrosAlmacenPeriodo
{
    public function obtener(PeriodoSemanal $periodo): Collection
    {
        return DB::query()
            ->fromSub($this->lineasPorCanjeProducto($periodo->idPlataforma), 'ag')
            ->join('dc_catalogo_productos as cdp', 'cdp.id', '=', 'ag.id_producto')
            ->leftJoin('dc_catalogo_proveedores as cp', 'cp.id', '=', DB::raw('COALESCE(ag.id_proveedor, cdp.id_proveedor)'))
            ->leftJoin('dc_orden_compra as oc', 'oc.id', '=', 'ag.id_orden_compra')
            ->whereBetween('ag.fecha_referencia', [
                $periodo->inicio->toDateString(),
                $periodo->fin->toDateString(),
            ])
            ->select(
                'ag.id_producto_almacen',
                'ag.id_canje',
                'ag.id_producto',
                'cdp.id_plataforma',
                DB::raw('COALESCE(ag.id_proveedor, cdp.id_proveedor) as id_proveedor'),
                'cp.nombre as nombre_proveedor',
                'cdp.nombre_producto',
                'cdp.sku',
                'cdp.marca',
                'cdp.costo_con_iva as precio',
                'ag.cantidad_gestionada',
                'ag.cantidad_entregada',
                'ag.tipo_envio',
                'ag.fecha_factura',
                'ag.folio_factura',
                'ag.precio_unitario_compra',
                'ag.total_compra',
                'ag.estatus',
                'ag.id_orden_compra',
                'oc.no_orden',
                'ag.fecha_referencia',
            )
            ->orderBy('ag.fecha_referencia')
            ->orderBy('cp.nombre')
            ->get();
    }

    private function lineasPorCanjeProducto(int $idPlataforma): Builder
    {
        return DB::table('dc_recepcion_almacen as ra')
            ->join('dc_catalogo_productos as cdp_f', 'cdp_f.id', '=', 'ra.id_producto')
            ->whereNotNull('ra.id_canje')
            ->where('cdp_f.id_plataforma', $idPlataforma)
            ->groupBy('ra.id_canje', 'ra.id_producto')
            ->select(
                'ra.id_canje',
                'ra.id_producto',
                DB::raw('MIN(ra.id) as id_producto_almacen'),
                DB::raw('MAX(ra.id_orden_compra) as id_orden_compra'),
                DB::raw('MAX(ra.id_proveedor) as id_proveedor'),
                DB::raw('MAX(ra.cantidad_producto) as cantidad_gestionada'),
                DB::raw('COALESCE(SUM(ra.cantidad_almacen), 0) as cantidad_entregada'),
                DB::raw('MAX(ra.tipo_envio) as tipo_envio'),
                DB::raw('MAX(ra.estatus) as estatus'),
                DB::raw('MAX(ra.fecha_compra) as fecha_factura'),
                DB::raw("GROUP_CONCAT(DISTINCT NULLIF(ra.folio_factura, '')) as folio_factura"),
                DB::raw('COALESCE(SUM(ra.precio_compra * ra.cantidad_almacen) / NULLIF(SUM(ra.cantidad_almacen), 0), MAX(ra.precio_compra)) as precio_unitario_compra'),
                DB::raw('COALESCE(SUM(ra.precio_compra * ra.cantidad_almacen), 0) as total_compra'),
                DB::raw('COALESCE(MAX(ra.fecha_compra), DATE(MIN(ra.created_at))) as fecha_referencia'),
            );
    }
}
