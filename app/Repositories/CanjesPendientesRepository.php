<?php

namespace App\Repositories;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CanjesPendientesRepository
{
    /** Validaciones listas para cotizar que aún no están en una orden de compra */
    public function base(): Builder
    {
        return DB::table('dc_validacion_canje as vc')
            ->join('dc_catalogo_productos as cdp', 'cdp.id', '=', 'vc.id_producto')
            ->where('vc.estatus', 'identidad_validada')
            ->where(fn($q) => $q->whereNull('vc.no_orden')->orWhere('vc.no_orden', ''))
            ->whereNull('vc.orden_ejecutada');
    }

    public function porProveedor(?int $idProveedor): Collection
    {
        return $this->base()
            ->leftJoin('dc_catalogo_proveedores as cp', 'cp.id', '=', 'vc.id_proveedor')
            ->select(
                'cdp.id',
                'vc.id as id_validacion_producto',
                'vc.id_canje',
                'cdp.id_plataforma',
                'cdp.sku',
                'vc.id_proveedor',
                'cdp.created_at as creacion_producto',
                'vc.estatus as estado_validacion',
                'vc.fecha_validacion',
                'vc.cantidad_producto as number_of_awards',
                'cdp.id as id_producto',
                'cdp.nombre_producto as nombre_premio',
                'cdp.marca',
                'cp.nombre as nombre_proveedor',
                'cdp.fee_brimagy',
                'cp.razon_social',
                'cdp.costo_sin_iva',
                'cdp.tipo_producto',
                DB::raw('NULL as estatus_proveedor')
            )
            ->when(
                $idProveedor === null,
                fn($q) => $q->where(fn($w) => $w->whereNull('vc.id_proveedor')->orWhere('vc.id_proveedor', 0)),
                fn($q) => $q->where('vc.id_proveedor', $idProveedor)
            )
            ->orderBy('vc.fecha_validacion', 'desc')
            ->get();
    }

    public function conteoPorProveedor(): Collection
    {
        return $this->base()
            ->selectRaw('COALESCE(vc.id_proveedor, 0) as id_proveedor, COUNT(*) as total')
            ->groupBy(DB::raw('COALESCE(vc.id_proveedor, 0)'))
            ->pluck('total', 'id_proveedor');
    }
}
