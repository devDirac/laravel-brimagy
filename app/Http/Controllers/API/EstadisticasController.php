<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Services\ReporteCanjesService;
use App\Models\User;
use App\Models\UserClub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/*
 * Todas las estadísticas usan ahora la MISMA lógica para ambas plataformas:
 *  - Se leen los canjes de swaps_view SIN joins ni GROUP BY (evita el error 1055
 *    de MySQL con ONLY_FULL_GROUP_BY sobre la vista).
 *  - El cruce con dc_catalogo_productos (por SKU o por nombre+talla) se hace en PHP
 *    con buildCatalogoLookups() + matchProducto(), igual que ya se hacía en club_bohn.
 *  - La única diferencia por plataforma es la conexión y el mapa de tipos de usuario.
 */
class EstadisticasController extends BaseController
{
    public $invalidFormatMessage = 'Formato invalido';

    public function __construct(private ReporteCanjesService $reporteCanjesService)
    {
    }

    /* =========================================================
     *  HOME
     * ========================================================= */
    public function getEstadisticasHome(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'periodo' => 'sometimes|in:semana,mes,año',
            ]);

            if ($validator->fails()) {
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $esClub = $this->esClubBohn($request);

            $tipoUsuarioMap = $esClub
                ? [4 => 'oso_polar', 5 => 'leon_marino', 6 => 'pingüino']
                : [1 => 'empleado_mabe', 2 => 'institucional', 3 => 'operario'];

            $usuarios = ($esClub ? UserClub::query() : User::query())
                ->select('id', 'tipo_usuario')
                ->get()
                ->keyBy('id');

            return $this->sendResponse(
                $this->construirEstadisticasHome($this->conexionSwaps($request), $tipoUsuarioMap, $usuarios)
            );
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener el dashboard de home', $th->getMessage(), 500);
        }
    }

    private function construirEstadisticasHome(?string $conexion, array $tipoUsuarioMap, $usuarios): array
    {
        $swaps = DB::connection($conexion)
            ->table('swaps_view as sp')
            ->select(
                'sp.id',
                'sp.folio',
                'sp.name as nombre_usuario',
                'sp.email',
                'sp.municipality as ciudad',
                'sp.phone as telefono',
                'sp.points_swap as puntos_canjeados',
                'sp.desc as nombre_premio',
                'sp.required_score as costo_premio',
                'sp.sku',
                'sp.size',
                'sp.status as estado_canje',
                'sp.created_at as creacion_canje',
                'sp.user_id'
            )
            ->orderBy('sp.created_at', 'desc')
            ->get();

        $validaciones = $this->ultimasValidaciones($swaps->pluck('id'), ['vc1.updated_at']);

        [$catalogoPorSku, $catalogoPorDescTalla] = $this->buildCatalogoLookups();

        $puntos_canjeados = 0;
        $puntos_sobrantes = 0;
        $statsPorTipo = [];
        $canjes = [];

        foreach ($swaps as $swap) {
            $validacion = $validaciones->get($swap->id);

            if ($validacion?->estatus === 'identidad_validada') {
                $puntos_canjeados += $swap->puntos_canjeados;
            } else {
                $puntos_sobrantes += $swap->puntos_canjeados;
            }

            $swap->fecha_validacion = $validacion?->updated_at;
            $swap->estado_validacion = $validacion?->estatus;
            $canjes[] = $swap;

            $tipo = $usuarios->get($swap->user_id)?->tipo_usuario;
            if (!$tipo) {
                continue;
            }

            $statsPorTipo[$tipo] ??= [
                'total_puntos' => 0,
                'total_canjes' => 0,
                'canjes_fisico' => 0,
                'canjes_digital' => 0,
            ];

            $statsPorTipo[$tipo]['total_puntos'] += $swap->puntos_canjeados;
            $statsPorTipo[$tipo]['total_canjes'] += 1;

            $producto = $this->matchProducto($catalogoPorSku, $catalogoPorDescTalla, $swap->sku, $swap->nombre_premio, $swap->size);

            if ($producto?->tipo_producto === 'fisico') {
                $statsPorTipo[$tipo]['canjes_fisico'] += 1;
            } elseif ($producto?->tipo_producto === 'digital') {
                $statsPorTipo[$tipo]['canjes_digital'] += 1;
            }
        }

        $participantesPorTipo = $usuarios->groupBy('tipo_usuario');

        $participantes_por_categoria = [];
        $puntos_por_usuario = [];

        foreach ($tipoUsuarioMap as $tipo => $nombre) {
            $participantes_por_categoria[] = [
                'tipo' => $tipo,
                'nombre' => $nombre,
                'total' => $participantesPorTipo->get($tipo)?->count() ?? 0,
            ];

            $row = $statsPorTipo[$tipo] ?? null;
            $total_canjes = (int) ($row['total_canjes'] ?? 0);
            $canjes_fisico = (int) ($row['canjes_fisico'] ?? 0);
            $canjes_digital = (int) ($row['canjes_digital'] ?? 0);

            $puntos_por_usuario[] = [
                'tipo' => $tipo,
                'nombre' => $nombre,
                'total_puntos' => $row['total_puntos'] ?? 0,
                'total_canjes' => $total_canjes,
                'canjes_fisico' => $canjes_fisico,
                'canjes_digital' => $canjes_digital,
                'porcentaje_fisico' => $total_canjes > 0 ? round(($canjes_fisico / $total_canjes) * 100, 1) : 0,
                'porcentaje_digital' => $total_canjes > 0 ? round(($canjes_digital / $total_canjes) * 100, 1) : 0,
            ];
        }

        return [
            'participantes' => $usuarios->count(),
            'puntos_canjeados' => $puntos_canjeados,
            'puntos_sobrantes' => $puntos_sobrantes,
            'puntos_acumulados' => $puntos_canjeados + $puntos_sobrantes,
            'canjes' => $canjes,
            'participantes_por_categoria' => $participantes_por_categoria,
            'puntos_por_usuario' => $puntos_por_usuario,
        ];
    }

    /* =========================================================
     *  CANJEADOS (físicos / digitales / sin clasificar)
     * ========================================================= */
    public function getEstadisticasCanjeados(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'fecha_inicio' => 'sometimes|nullable',
                'fecha_fin' => 'sometimes|nullable',
            ]);

            if ($validator->fails()) {
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $query = DB::connection($this->conexionSwaps($request))
                ->table('swaps_view as sp')
                ->select(
                    'sp.id',
                    'sp.folio',
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
                    'sp.status as estado_canje'
                );

            $this->aplicarFiltroFechas($query, $request, 'sp.created_at');

            $swaps = $query->orderBy('sp.created_at', 'desc')->get();

            $validaciones = $this->ultimasValidaciones($swaps->pluck('id'));

            [$catalogoPorSku, $catalogoPorDescTalla] = $this->buildCatalogoLookups();

            foreach ($swaps as $swap) {
                $producto = $this->matchProducto($catalogoPorSku, $catalogoPorDescTalla, $swap->sku, $swap->nombre_premio, $swap->size);

                $swap->id_producto = $producto->id ?? null;
                $swap->tipo_producto = $producto->tipo_producto ?? null;
                $swap->id_proveedor = $producto->id_proveedor ?? null;
                $swap->sku_catalogo = $producto->sku ?? null;
                $swap->estado_validacion = $validaciones->get($swap->id)?->estatus;
            }

            $fisicos = $swaps->where('tipo_producto', 'fisico')->values();
            $digitales = $swaps->where('tipo_producto', 'digital')->values();
            $sinTipo = $swaps->filter(fn($r) => !in_array($r->tipo_producto, ['fisico', 'digital']))->values();

            return $this->sendResponse([
                'total' => $swaps->count(),
                'fisicos' => ['total' => $fisicos->count(), 'canjes' => $fisicos],
                'digitales' => ['total' => $digitales->count(), 'canjes' => $digitales],
                'sin_clasificar' => ['total' => $sinTipo->count(), 'canjes' => $sinTipo],
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los canjeados', $th->getMessage(), 500);
        }
    }

    /* =========================================================
     *  PUNTOS POR CATEGORÍA
     * ========================================================= */
    public function getEstadisticasPuntosCategoria(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'fecha_inicio' => 'sometimes|nullable|date',
                'fecha_fin' => 'sometimes|nullable|date',
            ]);

            if ($validator->fails()) {
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $conexion = $this->conexionSwaps($request);

            $query = DB::connection($conexion)
                ->table('swaps_view as sp')
                ->select(
                    'sp.id',
                    'sp.folio',
                    'sp.name as nombre_usuario',
                    'sp.email',
                    'sp.phone',
                    'sp.points_swap as puntos_canjeados',
                    'sp.desc as nombre_premio',
                    'sp.required_score as costo_premio',
                    'sp.sku',
                    'sp.size',
                    'sp.created_at as creacion_canje',
                    'sp.status as estado_canje'
                );

            $this->aplicarFiltroFechas($query, $request, 'sp.created_at');

            $swaps = $query->orderBy('sp.created_at', 'desc')->get();

            // Las categorías (sub_categories) viven en la misma conexión que los canjes
            [$catalogoPorSku, $catalogoPorDescTalla] = $this->buildCatalogoLookups(true, $conexion);

            foreach ($swaps as $swap) {
                $producto = $this->matchProducto($catalogoPorSku, $catalogoPorDescTalla, $swap->sku, $swap->nombre_premio, $swap->size);

                $swap->id_producto = $producto->id ?? null;
                $swap->tipo_producto = $producto->tipo_producto ?? null;
                $swap->id_categoria = $producto->id_catalogo ?? null;
                $swap->nombre_categoria = $producto->nombre_categoria ?? null;
            }

            return $this->sendResponse([
                'fisicos' => $this->resumenPorTipo($swaps->where('tipo_producto', 'fisico')->values()),
                'digitales' => $this->resumenPorTipo($swaps->where('tipo_producto', 'digital')->values()),
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener las estadísticas', $th->getMessage(), 500);
        }
    }

    /* =========================================================
     *  PUNTOS POR TIPO DE PRODUCTO (mensual / anual)
     * ========================================================= */
    public function getEstadisticasPuntosPorTipoProducto(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'agrupacion' => 'sometimes|in:mensual,anual',
                'fecha_inicio' => 'sometimes|nullable|date',
                'fecha_fin' => 'sometimes|nullable|date',
            ]);

            if ($validator->fails()) {
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $agrupacion = $request->agrupacion ?? 'mensual';

            $query = DB::connection($this->conexionSwaps($request))
                ->table('swaps_view as sv')
                ->select('sv.id', 'sv.points_swap', 'sv.created_at', 'sv.sku', 'sv.desc', 'sv.size');

            $this->aplicarFiltroFechas($query, $request, 'sv.created_at');

            $swaps = $query->get();

            [$catalogoPorSku, $catalogoPorDescTalla] = $this->buildCatalogoLookups();

            $periodos = [];
            foreach ($swaps as $swap) {
                $producto = $this->matchProducto($catalogoPorSku, $catalogoPorDescTalla, $swap->sku, $swap->desc, $swap->size);
                $tipo = $this->normalizarTipo($producto->tipo_producto ?? null);

                $fecha = \Carbon\Carbon::parse($swap->created_at);
                $periodo = $agrupacion === 'anual' ? $fecha->format('Y') : $fecha->format('Y-m');

                $periodos[$periodo] ??= ['periodo' => $periodo] + $this->contadoresVacios();

                $periodos[$periodo][$tipo]['total_canjes'] += 1;
                $periodos[$periodo][$tipo]['total_puntos'] += (int) $swap->points_swap;
            }

            ksort($periodos);

            $resumen = $this->contadoresVacios();
            foreach ($periodos as $periodo) {
                foreach (['fisico', 'digital', 'sin_clasificar'] as $tipo) {
                    $resumen[$tipo]['total_canjes'] += $periodo[$tipo]['total_canjes'];
                    $resumen[$tipo]['total_puntos'] += $periodo[$tipo]['total_puntos'];
                }
            }

            return $this->sendResponse([
                'agrupacion' => $agrupacion,
                'resumen' => $resumen,
                'datos' => array_values($periodos),
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener estadísticas por tipo de producto', $th->getMessage(), 500);
        }
    }

    /* =========================================================
     *  COMPARATIVA ENTRE DOS PERIODOS
     * ========================================================= */
    public function getEstadisticasComparativa(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'periodo1_inicio' => ['sometimes', 'nullable', 'regex:/^\d{4}-\d{2}(-\d{2})?$/'],
                'periodo1_fin' => ['sometimes', 'nullable', 'regex:/^\d{4}-\d{2}(-\d{2})?$/'],
                'periodo2_inicio' => ['sometimes', 'nullable', 'regex:/^\d{4}-\d{2}(-\d{2})?$/'],
                'periodo2_fin' => ['sometimes', 'nullable', 'regex:/^\d{4}-\d{2}(-\d{2})?$/'],
            ]);

            if ($validator->fails()) {
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $completarFecha = function (string $fecha, bool $esInicio): string {
                if (preg_match('/^\d{4}-\d{2}$/', $fecha)) {
                    $carbon = \Carbon\Carbon::createFromFormat('Y-m', $fecha);
                    return $esInicio
                        ? $carbon->startOfMonth()->format('Y-m-d')
                        : $carbon->endOfMonth()->format('Y-m-d');
                }
                return $fecha;
            };

            $anioActual = now()->year;
            $anioAnterior = $anioActual - 1;

            $p1Inicio = $request->filled('periodo1_inicio') ? $completarFecha($request->periodo1_inicio, true) : "{$anioAnterior}-01-01";
            $p1Fin = $request->filled('periodo1_fin') ? $completarFecha($request->periodo1_fin, false) : "{$anioAnterior}-12-31";
            $p2Inicio = $request->filled('periodo2_inicio') ? $completarFecha($request->periodo2_inicio, true) : "{$anioActual}-01-01";
            $p2Fin = $request->filled('periodo2_fin') ? $completarFecha($request->periodo2_fin, false) : now()->format('Y-m-d');

            $esClub = $this->esClubBohn($request);
            $conexion = $this->conexionSwaps($request);
            $tipoMap = $esClub
                ? [4 => 'oso_polar', 5 => 'leon_marino', 6 => 'pinguino']
                : [1 => 'empleado_mabe', 2 => 'institucional', 3 => 'operario'];

            [$catalogoPorSku, $catalogoPorDescTalla] = $this->buildCatalogoLookups();

            $queryPeriodo = function (string $inicio, string $fin) use ($conexion, $catalogoPorSku, $catalogoPorDescTalla) {
                $ini = \Carbon\Carbon::parse($inicio)->startOfDay();
                $fin = \Carbon\Carbon::parse($fin)->endOfDay();
                if ($ini->gt($fin)) {
                    [$ini, $fin] = [$fin, $ini];
                }

                $rows = DB::connection($conexion)
                    ->table('swaps_view as sv')
                    ->join('users as u', 'u.id', '=', 'sv.user_id')
                    ->whereBetween('sv.created_at', [$ini, $fin])
                    ->select(
                        'sv.id',
                        'sv.points_swap',
                        'sv.sku',
                        'sv.desc',
                        'sv.size',
                        'sv.created_at',
                        'u.tipo_usuario'
                    )
                    ->get();

                foreach ($rows as $row) {
                    $producto = $this->matchProducto($catalogoPorSku, $catalogoPorDescTalla, $row->sku, $row->desc, $row->size);
                    $row->tipo_producto = $producto->tipo_producto ?? null;
                    $row->mes = \Carbon\Carbon::parse($row->created_at)->format('m');
                }

                return $rows;
            };

            $resumir = function ($rows) use ($tipoMap) {
                $resumen = $this->contadoresVacios();
                $porUsuario = [];

                foreach ($rows as $row) {
                    $tipo = $this->normalizarTipo($row->tipo_producto);

                    $resumen[$tipo]['total_canjes']++;
                    $resumen[$tipo]['total_puntos'] += (int) $row->points_swap;

                    $tu = $row->tipo_usuario;
                    $porUsuario[$tu] ??= ['total_canjes' => 0, 'total_puntos' => 0];
                    $porUsuario[$tu]['total_canjes']++;
                    $porUsuario[$tu]['total_puntos'] += (int) $row->points_swap;
                }

                $usuariosFinal = [];
                foreach ($tipoMap as $tipo => $nombre) {
                    $usuariosFinal[] = [
                        'tipo' => $tipo,
                        'nombre' => $nombre,
                        'total_canjes' => $porUsuario[$tipo]['total_canjes'] ?? 0,
                        'total_puntos' => $porUsuario[$tipo]['total_puntos'] ?? 0,
                    ];
                }

                return ['resumen' => $resumen, 'por_usuario' => $usuariosFinal];
            };

            $resumirPorMes = function ($rows) {
                $porMes = [];
                foreach ($rows as $row) {
                    $tipo = $this->normalizarTipo($row->tipo_producto);
                    $porMes[$row->mes] ??= $this->contadoresVacios();
                    $porMes[$row->mes][$tipo]['total_canjes']++;
                    $porMes[$row->mes][$tipo]['total_puntos'] += (int) $row->points_swap;
                }
                return $porMes;
            };

            $raw1 = $queryPeriodo($p1Inicio, $p1Fin);
            $raw2 = $queryPeriodo($p2Inicio, $p2Fin);

            $p1 = $resumir($raw1);
            $p2 = $resumir($raw2);

            $meses1 = $resumirPorMes($raw1);
            $meses2 = $resumirPorMes($raw2);

            $todosLosMeses = collect(array_merge(array_keys($meses1), array_keys($meses2)))
                ->unique()->sort()->values();

            $sumarPuntos = fn($d) => $d['fisico']['total_puntos'] + $d['digital']['total_puntos'] + $d['sin_clasificar']['total_puntos'];
            $sumarCanjes = fn($d) => $d['fisico']['total_canjes'] + $d['digital']['total_canjes'] + $d['sin_clasificar']['total_canjes'];

            $formatearMes = fn($d) => $d ? [
                'total_puntos' => $sumarPuntos($d),
                'total_canjes' => $sumarCanjes($d),
                'fisico_puntos' => $d['fisico']['total_puntos'],
                'digital_puntos' => $d['digital']['total_puntos'],
                'sin_clasificar_puntos' => $d['sin_clasificar']['total_puntos'],
            ] : null;

            $comparativa = $todosLosMeses->map(fn($mes) => [
                'mes' => $mes,
                'periodo1' => $formatearMes($meses1[$mes] ?? null),
                'periodo2' => $formatearMes($meses2[$mes] ?? null),
            ])->values();

            return $this->sendResponse([
                'periodo1' => [
                    'fecha_inicio' => $p1Inicio,
                    'fecha_fin' => $p1Fin,
                    'resumen' => $p1['resumen'],
                    'por_usuario' => $p1['por_usuario'],
                ],
                'periodo2' => [
                    'fecha_inicio' => $p2Inicio,
                    'fecha_fin' => $p2Fin,
                    'resumen' => $p2['resumen'],
                    'por_usuario' => $p2['por_usuario'],
                ],
                'comparativa' => $comparativa,
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener estadísticas comparativas', $th->getMessage(), 500);
        }
    }

    public function getReporteGeneralCanjes(Request $request)
    {
        try {
            $resultado = $this->reporteCanjesService->obtenerReporte($request);
            return $this->sendResponse($resultado);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener el reporte', $th->getMessage(), 500);
        }
    }

    /* =========================================================
     *  HELPERS
     * ========================================================= */

    private function esClubBohn(Request $request): bool
    {
        return $request->plataforma === 'club_bohn';
    }

    /** null = conexión por defecto */
    private function conexionSwaps(Request $request): ?string
    {
        return $this->esClubBohn($request) ? 'mysql_club_bohn' : null;
    }

    private function aplicarFiltroFechas($query, Request $request, string $columna): void
    {
        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $inicio = \Carbon\Carbon::parse($request->fecha_inicio)->startOfDay();
            $fin = \Carbon\Carbon::parse($request->fecha_fin)->endOfDay();

            if ($inicio->gt($fin)) {
                [$inicio, $fin] = [$fin, $inicio];
            }

            $query->whereBetween($columna, [$inicio, $fin]);
        } elseif ($request->filled('fecha_inicio')) {
            $query->whereDate($columna, '>=', $request->fecha_inicio);
        } elseif ($request->filled('fecha_fin')) {
            $query->whereDate($columna, '<=', $request->fecha_fin);
        }
    }

    /** Última validación (MAX id) de cada canje, indexada por id_canje. Siempre en la BD local. */
    private function ultimasValidaciones($idsCanje, array $columnasExtra = [])
    {
        if (collect($idsCanje)->isEmpty()) {
            return collect();
        }

        return DB::table('dc_validacion_canje as vc1')
            ->whereIn('vc1.id_canje', $idsCanje)
            ->whereRaw('vc1.id = (SELECT MAX(vc2.id) FROM dc_validacion_canje vc2 WHERE vc2.id_canje = vc1.id_canje)')
            ->select(array_merge(['vc1.id_canje', 'vc1.estatus'], $columnasExtra))
            ->get()
            ->keyBy('id_canje');
    }

    private function contadoresVacios(): array
    {
        return [
            'fisico' => ['total_canjes' => 0, 'total_puntos' => 0],
            'digital' => ['total_canjes' => 0, 'total_puntos' => 0],
            'sin_clasificar' => ['total_canjes' => 0, 'total_puntos' => 0],
        ];
    }

    private function normalizarTipo(?string $tipo): string
    {
        return in_array($tipo, ['fisico', 'digital'], true) ? $tipo : 'sin_clasificar';
    }

    private function resumenPorTipo($coleccion)
    {
        $porCategoria = $coleccion
            ->groupBy(fn($item) => $item->id_categoria ?? 0)
            ->map(function ($items) {
                $primero = $items->first();
                return [
                    'id_categoria' => $primero->id_categoria,
                    'nombre_categoria' => $primero->nombre_categoria ?? 'Sin categoría',
                    'total_canjes' => $items->count(),
                    'puntos_canjeados' => (int) $items->sum('puntos_canjeados'),
                ];
            })
            ->sortByDesc('puntos_canjeados')
            ->values();

        return [
            'total_canjes' => $coleccion->count(),
            'total_puntos_canjeados' => (int) $coleccion->sum('puntos_canjeados'),
            'top_categorias' => $porCategoria->take(10)->values(),
        ];
    }

    /**
     * Índices del catálogo local para cruzar canjes con productos.
     * $conexionCategorias: dónde vive sub_categories (null = conexión por defecto).
     */
    private function buildCatalogoLookups(bool $withCategoria = false, ?string $conexionCategorias = null): array
    {
        // Orden por id ascendente: si hay SKUs repetidos (p. ej. por "registrar nuevo precio"),
        // gana el producto más reciente.
        $catalogo = DB::table('dc_catalogo_productos as cdp')
            ->select('cdp.*')
            ->orderBy('cdp.id')
            ->get();

        $categoriasPorId = [];
        if ($withCategoria) {
            $categoriasPorId = DB::connection($conexionCategorias)
                ->table('sub_categories')
                ->pluck('desc', 'id');
        }

        $porSku = [];
        $porDescTalla = [];

        foreach ($catalogo as $prod) {
            if ($withCategoria) {
                $prod->nombre_categoria = $categoriasPorId[$prod->id_catalogo] ?? null;
            }

            if (!empty($prod->sku) && !in_array($prod->sku, ['N/A', '0'])) {
                $porSku[$prod->sku] = $prod;
            } else {
                $key = mb_strtolower(trim($prod->nombre_producto ?? '')) . '|' . mb_strtolower(trim($prod->talla ?? ''));
                $porDescTalla[$key] = $prod;
            }
        }

        return [$porSku, $porDescTalla];
    }

    private function matchProducto(array $porSku, array $porDescTalla, ?string $sku, ?string $desc, ?string $size)
    {
        if (!empty($sku) && !in_array($sku, ['N/A', '0']) && isset($porSku[$sku])) {
            return $porSku[$sku];
        }

        $key = mb_strtolower(trim($desc ?? '')) . '|' . mb_strtolower(trim($size ?? ''));
        return $porDescTalla[$key] ?? null;
    }
}