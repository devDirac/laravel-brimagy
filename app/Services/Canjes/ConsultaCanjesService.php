<?php

namespace App\Services\Canjes;

use App\Models\Plataformas;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consulta los canjes (una fila por producto) de puntotes o club bohn, con la última
 * validación de cada canje. Lógica movida tal cual desde CanjesController::consultarCanjes.
 */
class ConsultaCanjesService
{
    /** Columnas de swaps_view que se regresan al frontend. También las usa getCanjeById. */
    public const COLUMNAS = [
        'sp.id',
        'sp.folio',
        'sp.award_id',
        'sp.name as nombre_usuario',
        'sp.email',
        'sp.phone',
        'sp.number_of_awards',
        'sp.size',
        'sp.color',
        'sp.category',
        'sp.points_swap as puntos_canjeados',
        'sp.desc as nombre_premio',
        'sp.required_score as costo_premio',
        'sp.sku',
        'sp.street as calle',
        'sp.number as numero_calle',
        'sp.colony as colonia',
        'sp.postal_code as codigo_postal',
        'sp.municipality as municipio',
        'sp.inside as numero_interior',
        'sp.between_1',
        'sp.between_2',
        'sp.additional_reference as referencia_adicional',
        'sp.created_at as creacion_canje',
        'sp.status as estado_canje',
    ];

    public function consultar(FiltrosCanjes $filtros): Collection
    {
        $filas = $filtros->esClubBohn()
            ? $this->consultarClubBohn($filtros)
            : $this->consultarPuntotes($filtros);

        return $this->filtrarPorTipoProducto($filas, $filtros->tipoProducto);
    }

    private function consultarPuntotes(FiltrosCanjes $filtros): Collection
    {
        $idPuntotes = Plataformas::where('nombre', 'puntotes')->value('id');

        $query = DB::table('swaps_view as sp')
            ->leftJoin('dc_validacion_canje as vc', function ($join) use ($idPuntotes) {
                $join->on('vc.id_canje', '=', 'sp.id')
                    ->whereRaw(
                        'vc.id = (SELECT MAX(id) FROM dc_validacion_canje WHERE id_canje = sp.id AND id_plataforma = ?)',
                        [$idPuntotes]
                    );
            })
            ->leftJoin('dc_catalogo_productos as cdp', function ($join) use ($idPuntotes) {
                $join->on('sp.award_id', '=', 'cdp.id_producto_brimagy')
                    ->where('cdp.id_plataforma', '=', $idPuntotes);
            })
            ->select(array_merge(self::COLUMNAS, [
                'cdp.id as id_producto',
                'cdp.id_proveedor',
                'cdp.sku as sku_catalogo',
                'cdp.tipo_producto',
                'vc.estatus as estado_validacion',
            ]));

        if ($filtros->search) {
            $search = $filtros->search;
            $query->where(function ($q) use ($search) {
                $q->where('sp.folio', 'LIKE', "%{$search}%")
                    ->orWhere('sp.desc', 'LIKE', "%{$search}%")
                    ->orWhere('sp.email', 'LIKE', "%{$search}%")
                    ->orWhere('sp.sku', 'LIKE', "%{$search}%")
                    ->orWhere('sp.points_swap', 'LIKE', "%{$search}%")
                    ->orWhere('vc.estatus', 'LIKE', "%{$search}%");
            });
        }

        $this->aplicarFiltroFechas($query, $filtros);

        return $query->orderBy('sp.created_at', 'desc')->get();
    }

    private function consultarClubBohn(FiltrosCanjes $filtros): Collection
    {
        $idPlataforma = Plataformas::where('nombre', 'club bohn')->value('id');
        if (!$idPlataforma) {
            throw new \RuntimeException('La plataforma "club bohn" no existe');
        }

        $conexion = config('plataformas.conexiones_canjes.club bohn', 'mysql_club_bohn');

        $query = DB::connection($conexion)
            ->table('swaps_view as sp')
            ->select(self::COLUMNAS);

        if ($filtros->search) {
            $search = $filtros->search;
            $query->where(function ($q) use ($search) {
                $q->where('sp.folio', 'LIKE', "%{$search}%")
                    ->orWhere('sp.desc', 'LIKE', "%{$search}%")
                    ->orWhere('sp.email', 'LIKE', "%{$search}%")
                    ->orWhere('sp.sku', 'LIKE', "%{$search}%")
                    ->orWhere('sp.points_swap', 'LIKE', "%{$search}%");
            });
        }

        $this->aplicarFiltroFechas($query, $filtros);

        $swaps = $query->orderBy('sp.created_at', 'desc')->get();

        if ($swaps->isEmpty()) {
            return collect();
        }

        $catalogo = DB::table('dc_catalogo_productos')
            ->where('id_plataforma', $idPlataforma)
            ->whereIn('id_producto_brimagy', $swaps->pluck('award_id')->filter()->unique())
            ->select('id', 'id_proveedor', 'sku', 'id_producto_brimagy', 'tipo_producto')
            ->get()
            ->keyBy('id_producto_brimagy');

        // Última validación de cada canje, SOLO de club bohn
        $validaciones = DB::table('dc_validacion_canje')
            ->where('id_plataforma', $idPlataforma)
            ->whereIn('id_canje', $swaps->pluck('id')->unique())
            ->orderBy('id', 'desc')
            ->get()
            ->groupBy('id_canje')
            ->map(fn($grupo) => $grupo->first());

        $canjes = $swaps->map(function ($swap) use ($catalogo, $validaciones) {
            $producto = $catalogo->get($swap->award_id);
            $validacion = $validaciones->get($swap->id);

            return (object) array_merge((array) $swap, [
                'id_producto' => $producto->id ?? null,
                'id_proveedor' => $producto->id_proveedor ?? null,
                'sku_catalogo' => $producto->sku ?? null,
                'tipo_producto' => $producto->tipo_producto ?? null,
                'estado_validacion' => $validacion->estatus ?? null,
            ]);
        })->values();

        // Si la búsqueda no coincidió por otro campo, se intenta por estatus de validación
        if ($filtros->search) {
            $search = $filtros->search;
            $coincidePorOtroCampo = $canjes->contains(
                fn($c) =>
                    stripos($c->folio, $search) !== false
                    || stripos($c->nombre_premio, $search) !== false
                    || stripos($c->email, $search) !== false
                    || stripos($c->sku, $search) !== false
                    || stripos((string) $c->puntos_canjeados, $search) !== false
            );

            if (!$coincidePorOtroCampo) {
                $canjes = $canjes
                    ->filter(fn($c) => stripos((string) $c->estado_validacion, $search) !== false)
                    ->values();
            }
        }

        return $canjes;
    }

    private function aplicarFiltroFechas($query, FiltrosCanjes $filtros): void
    {
        if (empty($filtros->fecha1) || empty($filtros->fecha2)) {
            return;
        }

        $fecha1 = Carbon::parse($filtros->fecha1);
        $fecha2 = Carbon::parse($filtros->fecha2);
        [$inicio, $fin] = $fecha1->lt($fecha2) ? [$fecha1, $fecha2] : [$fecha2, $fecha1];

        $query->whereBetween('sp.created_at', [
            $inicio->copy()->startOfDay(),
            $fin->copy()->endOfDay(),
        ]);
    }

    /**
     * Filtra filas de canje por tipo_producto, conservando los productos sin catálogo
     * de los canjes que sí tienen al menos un producto de ese tipo.
     */
    private function filtrarPorTipoProducto(Collection $filas, ?string $tipo): Collection
    {
        if (!$tipo) {
            return $filas;
        }

        return $filas
            ->groupBy('id')
            ->filter(
                fn($grupo) =>
                    $grupo->contains('tipo_producto', $tipo)
                    || $grupo->every(fn($f) => $f->id_producto === null)
            )
            ->flatMap(fn($grupo) => $grupo->filter(
                fn($f) => $f->tipo_producto === $tipo || $f->id_producto === null
            ))
            ->values();
    }
}