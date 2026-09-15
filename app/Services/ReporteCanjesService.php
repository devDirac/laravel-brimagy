<?php
// app/Services/ReporteCanjesService.php

namespace App\Services;

use App\Models\Periodo;
use App\Models\Plataformas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteCanjesService
{

    public function obtenerReporte(Request $request): array
    {
        $plataforma = $request->plataforma === 'club_bohn' ? 'club bohn' : $request->plataforma;

        if ($request->plataforma === 'club_bohn') {
            return $this->reporteClubBohn($request);
        }

        return $this->reporteGeneral($request, $plataforma);
    }

    private function reporteGeneral(Request $request, string $plataforma): array
    {
        $plataformaModel = Plataformas::where('nombre', $plataforma)->first();
        if (!$plataformaModel) {
            throw new \RuntimeException("La plataforma {$plataforma} no existe");
        }
        $id_plataforma = $plataformaModel->id;

        $query = DB::table('swaps_view as sv')
            ->select(
                'sv.api_id',
                'sv.name as nombre',
                'sv.phone as telefono',
                'sv.email as correo',
                'sv.street as calle',
                'sv.number as no_ext',
                'sv.inside as no_int',
                'sv.colony as colonia',
                'sv.municipality as municipio_delegacion',
                'sv.postal_code as codigo_postal',
                DB::raw("CONCAT(sv.between_1, ' ', sv.between_2) as entre_calles"),
                'sv.additional_reference as referencia_adicional',
                'sv.folio as folio_canje',
                'sv.category as categoria',
                'sv.sub_category as subcategoria',
                'cdp.nombre_producto as premio',
                'cdp.sku',
                'sv.size as talla',
                'sv.color',
                'sv.number_of_awards as premios_canjeados',
                'sv.required_score as puntos_premio',
                'sv.points_swap as puntos_canjeados',
                'sv.created_at as fecha_canje',
                'ra.fecha_compra',
                'ra.folio_factura',
                'cdp.marca',
                'ra.imei',
                'cdp.costo_puntos_sin_iva as precio_sin_iva_puntos_prespuestado',
                'cdp.costo_sin_iva as precio_sin_iva_proveedor_prespuestado',
                'ra.precio_compra',
                DB::raw("(ra.precio_compra / 1.16) as precio_compra_sin_iva"),
                'cdp.fee_brimagy',
                DB::raw("(cdp.costo_puntos_sin_iva - COALESCE((ra.precio_compra / 1.16), 0)) as diferencia_precio_usuario"),
                DB::raw("(cdp.costo_sin_iva - COALESCE((ra.precio_compra / 1.16), 0)) as diferencia_precio_proveedor"),
                DB::raw("(cdp.costo_puntos_sin_iva * (COALESCE(cdp.fee_brimagy, 0) / 100.0)) as fee_presupuestado_puntos"),
                DB::raw("(cdp.costo_sin_iva * (COALESCE(cdp.fee_brimagy, 0) / 100.0)) as fee_presupuestado_proveedor_puntos"),
                DB::raw("((ra.precio_compra / 1.16) * (COALESCE(cdp.fee_brimagy, 0) / 100.0)) as fee_real"),
                DB::raw("((cdp.costo_puntos_sin_iva - (ra.precio_compra / 1.16)) * (COALESCE(cdp.fee_brimagy, 0) / 100.0)) as diferencia_precio_usuario_2"),
                DB::raw("((cdp.costo_sin_iva - (ra.precio_compra / 1.16)) * (COALESCE(cdp.fee_brimagy, 0) / 100.0)) as diferencia_precio_proveedor_2"),
                'cdp.envio_base as envio_presupuestado',
                'ra.costo_envio_real',
                DB::raw("(cdp.envio_base - ra.costo_envio_real) as diferencia_envio"),
                DB::raw("(cdp.costo_puntos_sin_iva + (cdp.costo_puntos_sin_iva * (COALESCE(cdp.fee_brimagy, 0) / 100.0)) + cdp.envio_base) as total_presupuestado_puntos"),
                DB::raw("(cdp.costo_sin_iva + (cdp.costo_sin_iva * (COALESCE(cdp.fee_brimagy, 0) / 100.0)) + cdp.envio_base) as total_presupuestado_proveedor_puntos"),
                DB::raw("(cdp.costo_sin_iva + (cdp.costo_sin_iva * (COALESCE(cdp.fee_brimagy, 0) / 100.0)) + ra.costo_envio_real) as total_real"),
                DB::raw("((cdp.costo_puntos_sin_iva - COALESCE((ra.precio_compra / 1.16), 0)) + ((cdp.costo_puntos_sin_iva - (ra.precio_compra / 1.16)) * (COALESCE(cdp.fee_brimagy, 0) / 100.0)) + (cdp.envio_base - ra.costo_envio_real)) as total_diferencia_puntos_usuario"),
                DB::raw("((cdp.costo_sin_iva - COALESCE((ra.precio_compra / 1.16), 0)) + ((cdp.costo_sin_iva - (ra.precio_compra / 1.16)) * (COALESCE(cdp.fee_brimagy, 0) / 100.0)) + (cdp.envio_base - ra.costo_envio_real)) as total_diferencia_puntos_proveedor"),
            )
            ->leftJoin('dc_catalogo_productos as cdp', 'sv.award_id', '=', 'cdp.id_producto_brimagy')
            ->leftJoin('dc_recepcion_almacen as ra', 'ra.id_canje', '=', 'sv.id')
            ->where('cdp.id_plataforma', $id_plataforma);

        $this->aplicarFiltroFechas($query, $request, 'sv.created_at');

        $reporte = $query->orderBy('ra.created_at', 'desc')->get();

        $periodoActual = Periodo::where('id_plataforma', $id_plataforma)->where('activo', 1)->first();

        $reporte = $reporte->map(function ($fila) use ($periodoActual) {
            $fechaCanje = $fila->fecha_canje;

            $mesPeriodo = calcularNombreMes($fechaCanje);
            $semanaPeriodo = null;

            if ($periodoActual) {
                try {
                    $semanaPeriodo = calcularNumSemana(
                        $periodoActual->fecha_inicio,
                        $periodoActual->fecha_fin,
                        $fechaCanje
                    );
                } catch (\InvalidArgumentException $e) {
                    $semanaPeriodo = "Fuera del periodo";
                }
            }

            $fila->mes_periodo = $mesPeriodo;
            $fila->semana_periodo = $semanaPeriodo;

            return $fila;
        });

        return [
            'detalle' => $reporte,
            'totales' => $this->calcularTotales($reporte),
        ];
    }

    private function reporteClubBohn(Request $request): array
    {
        $plataformaModel = Plataformas::where('nombre', 'club bohn')->first();
        if (!$plataformaModel) {
            throw new \RuntimeException('La plataforma club_bohn no existe');
        }
        $id_plataforma = $plataformaModel->id;

        $swapsQuery = DB::connection('mysql_club_bohn')
            ->table('swaps_view as sv')
            ->select(
                'sv.id',
                'sv.api_id',
                'sv.name as nombre',
                'sv.phone as telefono',
                'sv.email as correo',
                'sv.street as calle',
                'sv.number as no_ext',
                'sv.inside as no_int',
                'sv.colony as colonia',
                'sv.municipality as municipio_delegacion',
                'sv.postal_code as codigo_postal',
                DB::raw("CONCAT(sv.between_1, ' ', sv.between_2) as entre_calles"),
                'sv.additional_reference as referencia_adicional',
                'sv.folio as folio_canje',
                'sv.category as categoria',
                'sv.sub_category as subcategoria',
                'sv.size as talla',
                'sv.color',
                'sv.number_of_awards as premios_canjeados',
                'sv.required_score as puntos_premio',
                'sv.points_swap as puntos_canjeados',
                'sv.created_at as fecha_canje',
                'sv.award_id'
            );

        $this->aplicarFiltroFechas($swapsQuery, $request, 'sv.created_at');

        $swaps = $swapsQuery->orderBy('sv.created_at', 'desc')->get();

        if ($swaps->isEmpty()) {
            return ['detalle' => collect(), 'totales' => []];
        }

        $awardIds = $swaps->pluck('award_id')->filter()->unique()->values();

        $catalogo = DB::table('dc_catalogo_productos as cdp')
            ->where('cdp.id_plataforma', $id_plataforma)
            ->whereIn('cdp.id_producto_brimagy', $awardIds)
            ->select(
                'cdp.id_producto_brimagy',
                'cdp.nombre_producto as premio',
                'cdp.sku',
                'cdp.marca',
                'cdp.costo_puntos_sin_iva as precio_sin_iva_puntos_prespuestado',
                'cdp.costo_sin_iva as precio_sin_iva_proveedor_prespuestado',
                'cdp.fee_brimagy',
                'cdp.envio_base as envio_presupuestado'
            )
            ->get()
            ->keyBy('id_producto_brimagy');

        $swapIds = $swaps->pluck('id')->unique()->values();

        $recepciones = DB::table('dc_recepcion_almacen as ra')
            ->whereIn('ra.id_canje', $swapIds)
            ->select(
                'ra.id_canje',
                'ra.fecha_compra',
                'ra.folio_factura',
                'ra.imei',
                'ra.precio_compra',
                'ra.costo_envio_real'
            )
            ->get()
            ->keyBy('id_canje');

        $periodoActual = Periodo::where('id_plataforma', $id_plataforma)->where('activo', 1)->first();

        $reporte = $swaps->map(function ($swap) use ($catalogo, $recepciones, $periodoActual) {
            $producto = $catalogo->get($swap->award_id);
            $recepcion = $recepciones->get($swap->id);

            $fechaCanje = $swap->fecha_canje;

            $mesPeriodo = calcularNombreMes($fechaCanje);
            $semanaPeriodo = null;

            if ($periodoActual) {
                try {
                    $semanaPeriodo = calcularNumSemana($periodoActual->fecha_inicio, $periodoActual->fecha_fin, $fechaCanje);
                } catch (\InvalidArgumentException $e) {
                    $semanaPeriodo = "Fuera del periodo";
                }
            }


            $costo_puntos_sin_iva = $producto->precio_sin_iva_puntos_prespuestado ?? null;
            $costo_sin_iva = $producto->precio_sin_iva_proveedor_prespuestado ?? null;
            $fee_brimagy = $producto->fee_brimagy ?? 0;
            $envio_base = $producto->envio_presupuestado ?? null;

            $precio_compra = $recepcion->precio_compra ?? null;
            $costo_envio_real = $recepcion->costo_envio_real ?? null;
            $precio_compra_sin_iva = $precio_compra !== null ? ($precio_compra / 1.16) : null;

            $fee_factor = ($fee_brimagy ?? 0) / 100.0;

            $diferencia_precio_usuario = $costo_puntos_sin_iva !== null
                ? $costo_puntos_sin_iva - ($precio_compra_sin_iva ?? 0) : null;
            $diferencia_precio_proveedor = $costo_sin_iva !== null
                ? $costo_sin_iva - ($precio_compra_sin_iva ?? 0) : null;
            $fee_presupuestado_puntos = $costo_puntos_sin_iva !== null
                ? $costo_puntos_sin_iva * $fee_factor : null;
            $fee_presupuestado_proveedor_puntos = $costo_sin_iva !== null
                ? $costo_sin_iva * $fee_factor : null;
            $fee_real = $precio_compra_sin_iva !== null
                ? $precio_compra_sin_iva * $fee_factor : null;
            $diferencia_precio_usuario_2 = ($costo_puntos_sin_iva !== null && $precio_compra_sin_iva !== null)
                ? ($costo_puntos_sin_iva - $precio_compra_sin_iva) * $fee_factor : null;
            $diferencia_precio_proveedor_2 = ($costo_sin_iva !== null && $precio_compra_sin_iva !== null)
                ? ($costo_sin_iva - $precio_compra_sin_iva) * $fee_factor : null;
            $diferencia_envio = ($envio_base !== null && $costo_envio_real !== null)
                ? $envio_base - $costo_envio_real : null;
            $total_presupuestado_puntos = $costo_puntos_sin_iva !== null
                ? $costo_puntos_sin_iva + ($costo_puntos_sin_iva * $fee_factor) + ($envio_base ?? 0) : null;
            $total_presupuestado_proveedor_puntos = $costo_sin_iva !== null
                ? $costo_sin_iva + ($costo_sin_iva * $fee_factor) + ($envio_base ?? 0) : null;
            $total_real = $costo_sin_iva !== null
                ? $costo_sin_iva + ($costo_sin_iva * $fee_factor) + ($costo_envio_real ?? 0) : null;
            $total_diferencia_puntos_usuario = ($diferencia_precio_usuario !== null && $diferencia_precio_usuario_2 !== null && $diferencia_envio !== null)
                ? $diferencia_precio_usuario + $diferencia_precio_usuario_2 + $diferencia_envio : null;
            $total_diferencia_puntos_proveedor = ($diferencia_precio_proveedor !== null && $diferencia_precio_proveedor_2 !== null && $diferencia_envio !== null)
                ? $diferencia_precio_proveedor + $diferencia_precio_proveedor_2 + $diferencia_envio : null;


            return (object) array_merge((array) $swap, [
                'premio' => $producto->premio ?? null,
                'sku' => $producto->sku ?? null,
                'marca' => $producto->marca ?? null,
                'precio_sin_iva_puntos_prespuestado' => $costo_puntos_sin_iva,
                'precio_sin_iva_proveedor_prespuestado' => $costo_sin_iva,
                'fee_brimagy' => $fee_brimagy,
                'envio_presupuestado' => $envio_base,
                'fecha_compra' => $recepcion->fecha_compra ?? null,
                'folio_factura' => $recepcion->folio_factura ?? null,
                'imei' => $recepcion->imei ?? null,
                'precio_compra' => $precio_compra,
                'precio_compra_sin_iva' => $precio_compra_sin_iva,
                'costo_envio_real' => $costo_envio_real,
                'diferencia_precio_usuario' => $diferencia_precio_usuario,
                'diferencia_precio_proveedor' => $diferencia_precio_proveedor,
                'fee_presupuestado_puntos' => $fee_presupuestado_puntos,
                'fee_presupuestado_proveedor_puntos' => $fee_presupuestado_proveedor_puntos,
                'fee_real' => $fee_real,
                'diferencia_precio_usuario_2' => $diferencia_precio_usuario_2,
                'diferencia_precio_proveedor_2' => $diferencia_precio_proveedor_2,
                'diferencia_envio' => $diferencia_envio,
                'total_presupuestado_puntos' => $total_presupuestado_puntos,
                'total_presupuestado_proveedor_puntos' => $total_presupuestado_proveedor_puntos,
                'total_real' => $total_real,
                'total_diferencia_puntos_usuario' => $total_diferencia_puntos_usuario,
                'total_diferencia_puntos_proveedor' => $total_diferencia_puntos_proveedor,
                'mes_periodo' => $mesPeriodo,
                'semana_periodo' => $semanaPeriodo,
            ]);
        });

        return [
            'detalle' => $reporte->values(),
            'totales' => $this->calcularTotales($reporte),
        ];
    }


    private function aplicarFiltroFechas($query, Request $request, string $columna): void
    {
        if ($request->filled('fecha_inicio')) {
            $query->whereDate($columna, '>=', $request->fecha_inicio);
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate($columna, '<=', $request->fecha_fin);
        }
    }

    private function calcularTotales($reporte): array
    {
        $totales = [
            'precio_sin_iva_puntos_prespuestado' => $reporte->sum('precio_sin_iva_puntos_prespuestado'),
            'precio_sin_iva_proveedor_prespuestado' => $reporte->sum('precio_sin_iva_proveedor_prespuestado'),
            'precio_compra_sin_iva' => $reporte->sum('precio_compra_sin_iva'),
            'diferencia_precio_usuario' => $reporte->sum('diferencia_precio_usuario'),
            'diferencia_precio_proveedor' => $reporte->sum('diferencia_precio_proveedor'),
            'fee_presupuestado_puntos' => $reporte->sum('fee_presupuestado_puntos'),
            'fee_presupuestado_proveedor_puntos' => $reporte->sum('fee_presupuestado_proveedor_puntos'),
            'fee_real' => $reporte->sum('fee_real'),
            'diferencia_precio_usuario_2' => $reporte->sum('diferencia_precio_usuario_2'),
            'diferencia_precio_proveedor_2' => $reporte->sum('diferencia_precio_proveedor_2'),
            'envio_presupuestado' => $reporte->sum('envio_presupuestado'),
            'costo_envio_real' => $reporte->sum('costo_envio_real'),
            'diferencia_envio' => $reporte->sum('diferencia_envio'),
            'total_presupuestado_puntos' => $reporte->sum('total_presupuestado_puntos'),
            'total_presupuestado_proveedor_puntos' => $reporte->sum('total_presupuestado_proveedor_puntos'),
            'total_real' => $reporte->sum('total_real'),
            'total_diferencia_puntos_usuario' => $reporte->sum('total_diferencia_puntos_usuario'),
            'total_diferencia_puntos_proveedor' => $reporte->sum('total_diferencia_puntos_proveedor'),
        ];

        $pct = fn($num, $den) => $den ? round(($num / $den) * 100, 2) : 0;

        $totales['ahorro_precio_puntos_global'] = $pct($totales['diferencia_precio_usuario'], $totales['precio_sin_iva_puntos_prespuestado']);
        $totales['ahorro_precio_proveedor_global'] = $pct($totales['diferencia_precio_proveedor'], $totales['precio_sin_iva_proveedor_prespuestado']);
        $totales['ahorro_fee_puntos_global'] = $pct($totales['diferencia_precio_usuario_2'], $totales['fee_presupuestado_puntos']);
        $totales['ahorro_fee_proveedor_global'] = $pct($totales['diferencia_precio_proveedor_2'], $totales['fee_presupuestado_proveedor_puntos']);
        $totales['ahorro_envio_proveedor_global'] = $pct($totales['diferencia_envio'], $totales['envio_presupuestado']);
        $totales['ahorro_usuario_global'] = $pct($totales['total_diferencia_puntos_usuario'], $totales['total_presupuestado_puntos']);
        $totales['ahorro_proveedor_global'] = $pct($totales['total_diferencia_puntos_proveedor'], $totales['total_presupuestado_proveedor_puntos']);

        return $totales;
    }
}
