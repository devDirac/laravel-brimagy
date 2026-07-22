<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\User;
use App\Models\UserClub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EstadisticasController extends BaseController
{
    public $invalidFormatMessage = 'Formato invalido';

    public function getEstadisticasHome(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'periodo' => 'sometimes|in:semana,mes,año',
            ]);

            if ($validator->fails()) {
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            if ($request->plataforma === 'club_bohn') {
                $resultado = $this->getEstadisticasHomeClubBohn();
                return $this->sendResponse($resultado);
            }

            // Total participantes
            $participantes = User::get()->count();

            // Puntos de canjes completados (identidad_validada)
            $puntos_canjeados = DB::table('swaps_view as sp')
                ->leftJoin('dc_validacion_canje as vc', function ($join) {
                    $join->on('vc.id_canje', '=', 'sp.id')
                        ->whereRaw('vc.id = (SELECT MAX(id) FROM dc_validacion_canje WHERE id_canje = sp.id)');
                })
                ->where('vc.estatus', 'identidad_validada')
                ->sum('sp.points_swap');

            // Puntos sobrantes (canjes sin identidad_validada o sin validación)
            $puntos_sobrantes = DB::table('swaps_view as sp')
                ->leftJoin('dc_validacion_canje as vc', function ($join) {
                    $join->on('vc.id_canje', '=', 'sp.id')
                        ->whereRaw('vc.id = (SELECT MAX(id) FROM dc_validacion_canje WHERE id_canje = sp.id)');
                })
                ->where(function ($q) {
                    $q->where('vc.estatus', '!=', 'identidad_validada')
                        ->orWhereNull('vc.estatus');
                })
                ->sum('sp.points_swap');

            $puntos_acumulados = $puntos_canjeados + $puntos_sobrantes;

            // Lista de canjes con estatus
            $canjes = DB::table('swaps_view as sp')
                ->leftJoin('dc_validacion_canje as vc', function ($join) {
                    $join->on('vc.id_canje', '=', 'sp.id')
                        ->whereRaw('vc.id = (SELECT MAX(id) FROM dc_validacion_canje WHERE id_canje = sp.id)');
                })
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
                    'sp.status as estado_canje',
                    'sp.created_at as creacion_canje',
                    'vc.updated_at as fecha_validacion',
                    DB::raw('(SELECT vc.estatus FROM dc_validacion_canje vc WHERE vc.id_canje = sp.id LIMIT 1) as estado_validacion')
                )
                ->orderBy('sp.created_at', 'desc')
                ->get();

            // Participantes por categoría

            $participantes_por_tipo = User::select('tipo_usuario', DB::raw('count(*) as total'))
                ->groupBy('tipo_usuario')
                ->get()
                ->keyBy('tipo_usuario');

            $tipo_usuario_map = [
                1 => 'empleado_mabe',
                2 => 'institucional',
                3 => 'operario',
            ];

            $participantes_por_categoria = [];
            foreach ($tipo_usuario_map as $tipo => $nombre) {
                $participantes_por_categoria[] = [
                    'tipo'   => $tipo,
                    'nombre' => $nombre,
                    'total'  => $participantes_por_tipo->get($tipo)?->total ?? 0,
                ];
            }

            $stats_por_tipo = DB::table(function ($sub) {
                $sub->from('swaps_view as sv')
                    ->join('users as u', 'u.id', '=', 'sv.user_id')
                    ->leftJoin('dc_catalogo_productos as cdp', function ($join) {
                        $join->on(function ($query) {
                            $query->whereRaw("sv.sku IS NOT NULL AND sv.sku != '' AND sv.sku != 'N/A'")
                                ->whereColumn('sv.sku', '=', 'cdp.sku');
                        })->orOn(function ($query) {
                            $query->whereRaw("(cdp.sku IS NULL OR cdp.sku = '' OR cdp.sku = 'N/A')")
                                ->whereRaw("TRIM(LOWER(sv.desc)) = TRIM(LOWER(cdp.nombre_producto))")
                                ->whereRaw("TRIM(LOWER(sv.size)) = TRIM(LOWER(cdp.talla))");
                        });
                    })
                    ->select(
                        'sv.id',
                        'u.tipo_usuario',
                        'sv.points_swap',
                        DB::raw("MAX(CASE WHEN cdp.tipo_producto = 'fisico'  THEN 1 ELSE 0 END) as es_fisico"),
                        DB::raw("MAX(CASE WHEN cdp.tipo_producto = 'digital' THEN 1 ELSE 0 END) as es_digital")
                    )
                    ->groupBy('sv.id', 'u.tipo_usuario', 'sv.points_swap');
            }, 'sv_u')
                ->select(
                    'sv_u.tipo_usuario',
                    DB::raw('SUM(sv_u.points_swap) as total_puntos'),
                    DB::raw('COUNT(*) as total_canjes'),
                    DB::raw('SUM(sv_u.es_fisico) as canjes_fisico'),
                    DB::raw('SUM(sv_u.es_digital) as canjes_digital')
                )
                ->groupBy('sv_u.tipo_usuario')
                ->get()
                ->keyBy('tipo_usuario');

            $puntos_por_usuario = [];
            foreach ($tipo_usuario_map as $tipo => $nombre) {
                $row = $stats_por_tipo->get($tipo);

                $total_canjes   = (int) ($row?->total_canjes   ?? 0);
                $canjes_fisico  = (int) ($row?->canjes_fisico  ?? 0);
                $canjes_digital = (int) ($row?->canjes_digital ?? 0);

                $puntos_por_usuario[] = [
                    'tipo'               => $tipo,
                    'nombre'             => $nombre,
                    'total_puntos'       => $row?->total_puntos ?? 0,
                    'total_canjes'       => $total_canjes,
                    'canjes_fisico'      => $canjes_fisico,
                    'canjes_digital'     => $canjes_digital,
                    'porcentaje_fisico'  => $total_canjes > 0
                        ? round(($canjes_fisico  / $total_canjes) * 100, 1)
                        : 0,
                    'porcentaje_digital' => $total_canjes > 0
                        ? round(($canjes_digital / $total_canjes) * 100, 1)
                        : 0,
                ];
            }

            $resultado = [
                'participantes'            => $participantes,
                'puntos_canjeados'         => $puntos_canjeados,
                'puntos_sobrantes'         => $puntos_sobrantes,
                'puntos_acumulados'        => $puntos_acumulados,
                'canjes'                   => $canjes,
                'participantes_por_categoria' => $participantes_por_categoria,
                'puntos_por_usuario'     => $puntos_por_usuario,
            ];

            return $this->sendResponse($resultado);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener el dashboard de home', $th->getMessage(), 500);
        }
    }
    private function getEstadisticasHomeClubBohn(): array
    {
        $tipo_usuario_map = [
            4 => 'oso_polar',
            5 => 'leon_marino',
            6 => 'pingüino',
        ];

        // 1. Traer swaps y usuarios desde el servidor de club_bohn
        $swaps = DB::connection('mysql_club_bohn')
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

        $swapIds = $swaps->pluck('id');

        $participantes = UserClub::count();

        $usersClub = UserClub::select('id', 'tipo_usuario')
            ->get()
            ->keyBy('id');

        // 2. Traer validaciones locales (la más reciente por canje) solo para esos ids
        $validaciones = DB::table('dc_validacion_canje as vc1')
            ->whereIn('vc1.id_canje', $swapIds)
            ->whereRaw('vc1.id = (SELECT MAX(id) FROM dc_validacion_canje vc2 WHERE vc2.id_canje = vc1.id_canje)')
            ->select('vc1.id_canje', 'vc1.estatus', 'vc1.updated_at')
            ->get()
            ->keyBy('id_canje');

        // 3. Traer catálogo local para clasificar fisico/digital
        $catalogo = DB::table('dc_catalogo_productos')->get();

        $catalogoPorSku = [];
        $catalogoPorDescTalla = [];
        foreach ($catalogo as $prod) {
            if (!empty($prod->sku) && $prod->sku !== 'N/A') {
                $catalogoPorSku[$prod->sku] = $prod;
            } else {
                $key = mb_strtolower(trim($prod->nombre_producto)) . '|' . mb_strtolower(trim($prod->talla));
                $catalogoPorDescTalla[$key] = $prod;
            }
        }

        // 4. Recorrer swaps una sola vez y acumular todo lo necesario
        $puntos_canjeados = 0;
        $puntos_sobrantes = 0;
        $statsPorTipo = [];
        $canjes = [];

        foreach ($swaps as $swap) {
            $validacion = $validaciones->get($swap->id);
            $validado = $validacion?->estatus === 'identidad_validada';

            if ($validado) {
                $puntos_canjeados += $swap->puntos_canjeados;
            } else {
                $puntos_sobrantes += $swap->puntos_canjeados;
            }

            $swap->fecha_validacion   = $validacion?->updated_at;
            $swap->estado_validacion  = $validacion?->estatus;
            $canjes[] = $swap;

            $tipo = $usersClub->get($swap->user_id)?->tipo_usuario;
            if (!$tipo) {
                continue;
            }

            if (!isset($statsPorTipo[$tipo])) {
                $statsPorTipo[$tipo] = [
                    'total_puntos'   => 0,
                    'total_canjes'   => 0,
                    'canjes_fisico'  => 0,
                    'canjes_digital' => 0,
                ];
            }

            $statsPorTipo[$tipo]['total_puntos'] += $swap->puntos_canjeados;
            $statsPorTipo[$tipo]['total_canjes'] += 1;

            $producto = null;
            if (!empty($swap->sku) && $swap->sku !== 'N/A') {
                $producto = $catalogoPorSku[$swap->sku] ?? null;
            }
            if (!$producto) {
                $key = mb_strtolower(trim($swap->nombre_premio)) . '|' . mb_strtolower(trim($swap->size));
                $producto = $catalogoPorDescTalla[$key] ?? null;
            }

            if ($producto?->tipo_producto === 'fisico') {
                $statsPorTipo[$tipo]['canjes_fisico'] += 1;
            } elseif ($producto?->tipo_producto === 'digital') {
                $statsPorTipo[$tipo]['canjes_digital'] += 1;
            }
        }

        $puntos_acumulados = $puntos_canjeados + $puntos_sobrantes;

        // 5. Armar participantes_por_categoria
        $participantes_por_tipo = $usersClub->groupBy('tipo_usuario');
        $participantes_por_categoria = [];
        foreach ($tipo_usuario_map as $tipo => $nombre) {
            $participantes_por_categoria[] = [
                'tipo'   => $tipo,
                'nombre' => $nombre,
                'total'  => $participantes_por_tipo->get($tipo)?->count() ?? 0,
            ];
        }

        // 6. Armar puntos_por_usuario
        $puntos_por_usuario = [];
        foreach ($tipo_usuario_map as $tipo => $nombre) {
            $row = $statsPorTipo[$tipo] ?? null;

            $total_canjes   = (int) ($row['total_canjes']   ?? 0);
            $canjes_fisico  = (int) ($row['canjes_fisico']  ?? 0);
            $canjes_digital = (int) ($row['canjes_digital'] ?? 0);

            $puntos_por_usuario[] = [
                'tipo'               => $tipo,
                'nombre'             => $nombre,
                'total_puntos'       => $row['total_puntos'] ?? 0,
                'total_canjes'       => $total_canjes,
                'canjes_fisico'      => $canjes_fisico,
                'canjes_digital'     => $canjes_digital,
                'porcentaje_fisico'  => $total_canjes > 0
                    ? round(($canjes_fisico  / $total_canjes) * 100, 1)
                    : 0,
                'porcentaje_digital' => $total_canjes > 0
                    ? round(($canjes_digital / $total_canjes) * 100, 1)
                    : 0,
            ];
        }

        return [
            'participantes'                => $participantes,
            'puntos_canjeados'              => $puntos_canjeados,
            'puntos_sobrantes'              => $puntos_sobrantes,
            'puntos_acumulados'             => $puntos_acumulados,
            'canjes'                        => collect($canjes)->sortByDesc('creacion_canje')->values(),
            'participantes_por_categoria'   => $participantes_por_categoria,
            'puntos_por_usuario'            => $puntos_por_usuario,
        ];
    }

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

            if ($request->plataforma === 'club_bohn') {
                return $this->sendResponse($this->getEstadisticasCanjeadosClubBohn($request));
            }

            $query = DB::table('swaps_view as sp')
                ->leftJoin('dc_validacion_canje as vc', function ($join) {
                    $join->on('vc.id_canje', '=', 'sp.id')
                        ->whereRaw('vc.id = (SELECT MAX(id) FROM dc_validacion_canje WHERE id_canje = sp.id)');
                })
                ->leftJoin('dc_catalogo_productos as cdp', function ($join) {
                    $join->on(function ($q) {
                        $q->whereRaw("sp.sku IS NOT NULL AND sp.sku != '' AND sp.sku != 'N/A' AND sp.sku != '0'")
                            ->whereColumn('sp.sku', '=', 'cdp.sku');
                    })->orOn(function ($q) {
                        $q->whereRaw("(sp.sku IS NULL OR sp.sku = '' OR sp.sku = 'N/A' OR sp.sku = '0')")
                            ->whereRaw("TRIM(LOWER(sp.desc)) = TRIM(LOWER(cdp.nombre_producto))")
                            ->whereRaw("TRIM(LOWER(sp.size)) = TRIM(LOWER(cdp.talla))");
                    });
                })
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
                    'sp.status as estado_canje',
                    DB::raw('MIN(cdp.id) as id_producto'),
                    DB::raw('MIN(cdp.tipo_producto) as tipo_producto'),
                    DB::raw('MIN(cdp.id_proveedor) as id_proveedor'),
                    DB::raw('MIN(cdp.sku) as sku_catalogo'),
                    DB::raw('(SELECT vc2.estatus FROM dc_validacion_canje vc2 WHERE vc2.id_canje = sp.id ORDER BY vc2.id DESC LIMIT 1) as estado_validacion')
                )
                ->groupBy(
                    'sp.id',
                    'sp.folio',
                    'sp.name',
                    'sp.email',
                    'sp.phone',
                    'sp.number_of_awards',
                    'sp.size',
                    'sp.color',
                    'sp.category',
                    'sp.points_swap',
                    'sp.desc',
                    'sp.required_score',
                    'sp.sku',
                    'sp.street',
                    'sp.number',
                    'sp.colony',
                    'sp.postal_code',
                    'sp.municipality',
                    'sp.inside',
                    'sp.between_1',
                    'sp.between_2',
                    'sp.additional_reference',
                    'sp.created_at',
                    'sp.status'
                );

            // Filtro por fechas
            if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
                $inicio = \Carbon\Carbon::parse($request->fecha_inicio)->startOfDay();
                $fin = \Carbon\Carbon::parse($request->fecha_fin)->endOfDay();

                if ($inicio->gt($fin)) {
                    [$inicio, $fin] = [$fin, $inicio];
                }

                $query->whereBetween('sp.created_at', [$inicio, $fin]);
            } elseif ($request->filled('fecha_inicio')) {
                $query->whereDate('sp.created_at', '>=', $request->fecha_inicio);
            } elseif ($request->filled('fecha_fin')) {
                $query->whereDate('sp.created_at', '<=', $request->fecha_fin);
            }

            $todos = $query->orderBy('sp.created_at', 'desc')->get();

            // Agrupar por tipo_producto
            $fisicos = $todos->where('tipo_producto', 'fisico')->values();
            $digitales = $todos->where('tipo_producto', 'digital')->values();
            $sin_tipo = $todos->filter(fn($r) => !in_array($r->tipo_producto, ['fisico', 'digital']))->values();

            return $this->sendResponse([
                'total' => $todos->count(),
                'fisicos' => [
                    'total' => $fisicos->count(),
                    'canjes' => $fisicos,
                ],
                'digitales' => [
                    'total' => $digitales->count(),
                    'canjes' => $digitales,
                ],
                'sin_clasificar' => [
                    'total' => $sin_tipo->count(),
                    'canjes' => $sin_tipo,
                ],
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los canjeados', $th->getMessage(), 500);
        }
    }
    private function getEstadisticasCanjeadosClubBohn(Request $request): array
    {
        $query = DB::connection('mysql_club_bohn')
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

        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $inicio = \Carbon\Carbon::parse($request->fecha_inicio)->startOfDay();
            $fin = \Carbon\Carbon::parse($request->fecha_fin)->endOfDay();
            if ($inicio->gt($fin)) [$inicio, $fin] = [$fin, $inicio];
            $query->whereBetween('sp.created_at', [$inicio, $fin]);
        } elseif ($request->filled('fecha_inicio')) {
            $query->whereDate('sp.created_at', '>=', $request->fecha_inicio);
        } elseif ($request->filled('fecha_fin')) {
            $query->whereDate('sp.created_at', '<=', $request->fecha_fin);
        }

        $swaps = $query->orderBy('sp.created_at', 'desc')->get();
        $swapIds = $swaps->pluck('id');

        $validaciones = DB::table('dc_validacion_canje as vc1')
            ->whereIn('vc1.id_canje', $swapIds)
            ->whereRaw('vc1.id = (SELECT MAX(id) FROM dc_validacion_canje vc2 WHERE vc2.id_canje = vc1.id_canje)')
            ->select('vc1.id_canje', 'vc1.estatus')
            ->get()
            ->keyBy('id_canje');

        [$catalogoPorSku, $catalogoPorDescTalla] = $this->buildCatalogoLookups();

        foreach ($swaps as $swap) {
            $producto = $this->matchProducto($catalogoPorSku, $catalogoPorDescTalla, $swap->sku, $swap->nombre_premio, $swap->size);

            $swap->id_producto       = $producto->id ?? null;
            $swap->tipo_producto     = $producto->tipo_producto ?? null;
            $swap->id_proveedor      = $producto->id_proveedor ?? null;
            $swap->sku_catalogo      = $producto->sku ?? null;
            $swap->estado_validacion = $validaciones->get($swap->id)?->estatus;
        }

        $fisicos   = $swaps->where('tipo_producto', 'fisico')->values();
        $digitales = $swaps->where('tipo_producto', 'digital')->values();
        $sinTipo   = $swaps->filter(fn($r) => !in_array($r->tipo_producto, ['fisico', 'digital']))->values();

        return [
            'total' => $swaps->count(),
            'fisicos' => ['total' => $fisicos->count(), 'canjes' => $fisicos],
            'digitales' => ['total' => $digitales->count(), 'canjes' => $digitales],
            'sin_clasificar' => ['total' => $sinTipo->count(), 'canjes' => $sinTipo],
        ];
    }
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

            if ($request->plataforma === 'club_bohn') {
                return $this->sendResponse($this->getEstadisticasPuntosCategoriaClubBohn($request));
            }

            $query = DB::table('swaps_view as sp')
                ->leftJoin('dc_validacion_canje as vc', function ($join) {
                    $join->on('vc.id_canje', '=', 'sp.id')
                        ->whereRaw('vc.id = (SELECT MAX(id) FROM dc_validacion_canje WHERE id_canje = sp.id)');
                })
                ->leftJoin('dc_catalogo_productos as cdp', function ($join) {
                    $join->on(function ($q) {
                        $q->whereRaw("sp.sku IS NOT NULL AND sp.sku != '' AND sp.sku != 'N/A' AND sp.sku != '0'")
                            ->whereColumn('sp.sku', '=', 'cdp.sku');
                    })->orOn(function ($q) {
                        $q->whereRaw("(sp.sku IS NULL OR sp.sku = '' OR sp.sku = 'N/A' OR sp.sku = '0')")
                            ->whereRaw("TRIM(LOWER(sp.desc)) = TRIM(LOWER(cdp.nombre_producto))")
                            ->whereRaw("TRIM(LOWER(sp.size)) = TRIM(LOWER(cdp.talla))");
                    });
                })
                ->leftJoin('sub_categories as sc', 'sc.id', '=', 'cdp.id_catalogo')
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
                    'sp.created_at as creacion_canje',
                    'sp.status as estado_canje',
                    DB::raw('MIN(cdp.id) as id_producto'),
                    DB::raw('MIN(cdp.tipo_producto) as tipo_producto'),
                    DB::raw('MIN(cdp.id_catalogo) as id_categoria'),
                    DB::raw('MIN(sc.desc) as nombre_categoria'),
                    DB::raw('(SELECT vc2.estatus FROM dc_validacion_canje vc2 WHERE vc2.id_canje = sp.id ORDER BY vc2.id DESC LIMIT 1) as estado_validacion')
                )
                ->groupBy(
                    'sp.id',
                    'sp.folio',
                    'sp.name',
                    'sp.email',
                    'sp.phone',
                    'sp.points_swap',
                    'sp.desc',
                    'sp.required_score',
                    'sp.sku',
                    'sp.created_at',
                    'sp.status'
                );

            // Filtro por fechas
            if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
                $inicio = \Carbon\Carbon::parse($request->fecha_inicio)->startOfDay();
                $fin = \Carbon\Carbon::parse($request->fecha_fin)->endOfDay();

                if ($inicio->gt($fin)) {
                    [$inicio, $fin] = [$fin, $inicio];
                }

                $query->whereBetween('sp.created_at', [$inicio, $fin]);
            } elseif ($request->filled('fecha_inicio')) {
                $query->whereDate('sp.created_at', '>=', $request->fecha_inicio);
            } elseif ($request->filled('fecha_fin')) {
                $query->whereDate('sp.created_at', '<=', $request->fecha_fin);
            }

            $todos = $query->orderBy('sp.created_at', 'desc')->get();

            // Separar por tipo de producto
            $fisicos = $todos->where('tipo_producto', 'fisico')->values();
            $digitales = $todos->where('tipo_producto', 'digital')->values();

            return $this->sendResponse([
                'fisicos' => $this->resumenPorTipo($fisicos),
                'digitales' => $this->resumenPorTipo($digitales),
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener las estadísticas', $th->getMessage(), 500);
        }
    }
    private function getEstadisticasPuntosCategoriaClubBohn(Request $request): array
    {
        $query = DB::connection('mysql_club_bohn')
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

        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $inicio = \Carbon\Carbon::parse($request->fecha_inicio)->startOfDay();
            $fin = \Carbon\Carbon::parse($request->fecha_fin)->endOfDay();
            if ($inicio->gt($fin)) [$inicio, $fin] = [$fin, $inicio];
            $query->whereBetween('sp.created_at', [$inicio, $fin]);
        } elseif ($request->filled('fecha_inicio')) {
            $query->whereDate('sp.created_at', '>=', $request->fecha_inicio);
        } elseif ($request->filled('fecha_fin')) {
            $query->whereDate('sp.created_at', '<=', $request->fecha_fin);
        }

        $swaps = $query->orderBy('sp.created_at', 'desc')->get();

        [$catalogoPorSku, $catalogoPorDescTalla] = $this->buildCatalogoLookups(withCategoria: true);

        foreach ($swaps as $swap) {
            $producto = $this->matchProducto($catalogoPorSku, $catalogoPorDescTalla, $swap->sku, $swap->nombre_premio, $swap->size);

            $swap->id_producto      = $producto->id ?? null;
            $swap->tipo_producto    = $producto->tipo_producto ?? null;
            $swap->id_categoria     = $producto->id_catalogo ?? null;
            $swap->nombre_categoria = $producto->nombre_categoria ?? null;
        }

        $fisicos   = $swaps->where('tipo_producto', 'fisico')->values();
        $digitales = $swaps->where('tipo_producto', 'digital')->values();

        return [
            'fisicos'   => $this->resumenPorTipo($fisicos),
            'digitales' => $this->resumenPorTipo($digitales),
        ];
    }

    public function getEstadisticasPuntosPorTipoProducto(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'agrupacion'   => 'sometimes|in:mensual,anual',
                'fecha_inicio' => 'sometimes|nullable|date',
                'fecha_fin'    => 'sometimes|nullable|date',
            ]);

            if ($validator->fails()) {
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            if ($request->plataforma === 'club_bohn') {
                return $this->sendResponse($this->getEstadisticasPuntosPorTipoProductoClubBohn($request));
            }

            $agrupacion = $request->agrupacion ?? "mensual";
            $formatoFecha = $agrupacion === 'anual'
                ? DB::raw("DATE_FORMAT(sv.created_at, '%Y') as periodo")
                : DB::raw("DATE_FORMAT(sv.created_at, '%Y-%m') as periodo");

            $query = DB::table(function ($sub) use ($formatoFecha) {
                $sub->from('swaps_view as sv')
                    ->leftJoin('dc_catalogo_productos as cdp', function ($join) {
                        $join->on(function ($q) {
                            $q->whereRaw("sv.sku IS NOT NULL AND sv.sku != '' AND sv.sku != 'N/A' AND sv.sku != '0'")
                                ->whereColumn('sv.sku', '=', 'cdp.sku');
                        })->orOn(function ($q) {
                            $q->whereRaw("(sv.sku IS NULL OR sv.sku = '' OR sv.sku = 'N/A' OR sv.sku = '0')")
                                ->whereRaw("TRIM(LOWER(sv.desc)) = TRIM(LOWER(cdp.nombre_producto))")
                                ->whereRaw("TRIM(LOWER(sv.size)) = TRIM(LOWER(cdp.talla))");
                        });
                    })
                    ->select(
                        'sv.id',
                        'sv.points_swap',
                        'sv.created_at',
                        $formatoFecha,
                        DB::raw('MIN(cdp.tipo_producto) as tipo_producto')
                    )
                    ->groupBy('sv.id', 'sv.points_swap', 'sv.created_at');
            }, 'base');

            // Filtro por fechas
            if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
                $inicio = \Carbon\Carbon::parse($request->fecha_inicio)->startOfDay();
                $fin    = \Carbon\Carbon::parse($request->fecha_fin)->endOfDay();

                if ($inicio->gt($fin)) {
                    [$inicio, $fin] = [$fin, $inicio];
                }

                $query->whereBetween('base.created_at', [$inicio, $fin]);
            } elseif ($request->filled('fecha_inicio')) {
                $query->whereDate('base.created_at', '>=', $request->fecha_inicio);
            } elseif ($request->filled('fecha_fin')) {
                $query->whereDate('base.created_at', '<=', $request->fecha_fin);
            }

            $resultados = $query
                ->select(
                    'base.periodo',
                    'base.tipo_producto',
                    DB::raw('COUNT(base.id)          as total_canjes'),
                    DB::raw('SUM(base.points_swap)   as total_puntos')
                )
                ->groupBy('base.periodo', 'base.tipo_producto')
                ->orderBy('base.periodo', 'asc')
                ->get();

            // Agrupar por periodo con fisico/digital separados
            $periodos = [];
            foreach ($resultados as $row) {
                $p = $row->periodo;
                if (!isset($periodos[$p])) {
                    $periodos[$p] = [
                        'periodo'        => $p,
                        'fisico'  => ['total_canjes' => 0, 'total_puntos' => 0],
                        'digital' => ['total_canjes' => 0, 'total_puntos' => 0],
                        'sin_clasificar' => ['total_canjes' => 0, 'total_puntos' => 0],
                    ];
                }

                $tipo = $row->tipo_producto ?? 'sin_clasificar';
                if (!in_array($tipo, ['fisico', 'digital'])) {
                    $tipo = 'sin_clasificar';
                }

                $periodos[$p][$tipo]['total_canjes'] += (int) $row->total_canjes;
                $periodos[$p][$tipo]['total_puntos'] += (int) $row->total_puntos;
            }

            $resumen = [
                'fisico'         => ['total_canjes' => 0, 'total_puntos' => 0],
                'digital'        => ['total_canjes' => 0, 'total_puntos' => 0],
                'sin_clasificar' => ['total_canjes' => 0, 'total_puntos' => 0],
            ];

            foreach ($periodos as $periodo) {
                foreach (['fisico', 'digital', 'sin_clasificar'] as $tipo) {
                    $resumen[$tipo]['total_canjes'] += $periodo[$tipo]['total_canjes'];
                    $resumen[$tipo]['total_puntos'] += $periodo[$tipo]['total_puntos'];
                }
            }

            return $this->sendResponse([
                'agrupacion' => $agrupacion,
                'resumen'    => $resumen,
                'datos'      => array_values($periodos),

            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener estadísticas por tipo de producto', $th->getMessage(), 500);
        }
    }
    private function getEstadisticasPuntosPorTipoProductoClubBohn(Request $request): array
    {
        $agrupacion = $request->agrupacion ?? 'mensual';

        $query = DB::connection('mysql_club_bohn')
            ->table('swaps_view as sv')
            ->select('sv.id', 'sv.points_swap', 'sv.created_at', 'sv.sku', 'sv.desc', 'sv.size');

        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $inicio = \Carbon\Carbon::parse($request->fecha_inicio)->startOfDay();
            $fin    = \Carbon\Carbon::parse($request->fecha_fin)->endOfDay();
            if ($inicio->gt($fin)) [$inicio, $fin] = [$fin, $inicio];
            $query->whereBetween('sv.created_at', [$inicio, $fin]);
        } elseif ($request->filled('fecha_inicio')) {
            $query->whereDate('sv.created_at', '>=', $request->fecha_inicio);
        } elseif ($request->filled('fecha_fin')) {
            $query->whereDate('sv.created_at', '<=', $request->fecha_fin);
        }

        $swaps = $query->get();

        [$catalogoPorSku, $catalogoPorDescTalla] = $this->buildCatalogoLookups();

        $periodos = [];
        foreach ($swaps as $swap) {
            $producto = $this->matchProducto($catalogoPorSku, $catalogoPorDescTalla, $swap->sku, $swap->desc, $swap->size);
            $tipo = $producto->tipo_producto ?? 'sin_clasificar';
            if (!in_array($tipo, ['fisico', 'digital'])) {
                $tipo = 'sin_clasificar';
            }

            $fecha   = \Carbon\Carbon::parse($swap->created_at);
            $periodo = $agrupacion === 'anual' ? $fecha->format('Y') : $fecha->format('Y-m');

            if (!isset($periodos[$periodo])) {
                $periodos[$periodo] = [
                    'periodo'        => $periodo,
                    'fisico'         => ['total_canjes' => 0, 'total_puntos' => 0],
                    'digital'        => ['total_canjes' => 0, 'total_puntos' => 0],
                    'sin_clasificar' => ['total_canjes' => 0, 'total_puntos' => 0],
                ];
            }

            $periodos[$periodo][$tipo]['total_canjes'] += 1;
            $periodos[$periodo][$tipo]['total_puntos'] += (int) $swap->points_swap;
        }

        ksort($periodos);

        $resumen = [
            'fisico'         => ['total_canjes' => 0, 'total_puntos' => 0],
            'digital'        => ['total_canjes' => 0, 'total_puntos' => 0],
            'sin_clasificar' => ['total_canjes' => 0, 'total_puntos' => 0],
        ];

        foreach ($periodos as $periodo) {
            foreach (['fisico', 'digital', 'sin_clasificar'] as $tipo) {
                $resumen[$tipo]['total_canjes'] += $periodo[$tipo]['total_canjes'];
                $resumen[$tipo]['total_puntos'] += $periodo[$tipo]['total_puntos'];
            }
        }

        return [
            'agrupacion' => $agrupacion,
            'resumen'    => $resumen,
            'datos'      => array_values($periodos),
        ];
    }

    public function getEstadisticasComparativa(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'periodo1_inicio' => ['sometimes', 'nullable', 'regex:/^\d{4}-\d{2}(-\d{2})?$/'],
                'periodo1_fin'    => ['sometimes', 'nullable', 'regex:/^\d{4}-\d{2}(-\d{2})?$/'],
                'periodo2_inicio' => ['sometimes', 'nullable', 'regex:/^\d{4}-\d{2}(-\d{2})?$/'],
                'periodo2_fin'    => ['sometimes', 'nullable', 'regex:/^\d{4}-\d{2}(-\d{2})?$/'],
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

            $anioActual   = now()->year;
            $anioAnterior = $anioActual - 1;

            $p1Inicio = $request->filled('periodo1_inicio') ? $completarFecha($request->periodo1_inicio, true)  : "{$anioAnterior}-01-01";
            $p1Fin    = $request->filled('periodo1_fin')    ? $completarFecha($request->periodo1_fin, false)    : "{$anioAnterior}-12-31";
            $p2Inicio = $request->filled('periodo2_inicio') ? $completarFecha($request->periodo2_inicio, true)  : "{$anioActual}-01-01";
            $p2Fin    = $request->filled('periodo2_fin')    ? $completarFecha($request->periodo2_fin, false)    : now()->format('Y-m-d');

            if ($request->plataforma === 'club_bohn') {
                return $this->sendResponse(
                    $this->getEstadisticasComparativaClubBohn($p1Inicio, $p1Fin, $p2Inicio, $p2Fin)
                );
            }

            $queryPeriodo = function (string $inicio, string $fin) {
                $ini = \Carbon\Carbon::parse($inicio)->startOfDay();
                $fin = \Carbon\Carbon::parse($fin)->endOfDay();
                if ($ini->gt($fin)) [$ini, $fin] = [$fin, $ini];

                return DB::table('swaps_view as sv')
                    ->leftJoin('dc_catalogo_productos as cdp', function ($join) {
                        $join->on(function ($q) {
                            $q->whereRaw("sv.sku IS NOT NULL AND sv.sku != '' AND sv.sku != 'N/A' AND sv.sku != '0'")
                                ->whereColumn('sv.sku', 'cdp.sku');
                        })->orOn(function ($q) {
                            $q->whereRaw("(sv.sku IS NULL OR sv.sku = '' OR sv.sku = 'N/A' OR sv.sku = '0')")
                                ->whereRaw("TRIM(LOWER(sv.desc)) = TRIM(LOWER(cdp.nombre_producto))")
                                ->whereRaw("TRIM(LOWER(sv.size)) = TRIM(LOWER(cdp.talla))");
                        });
                    })
                    ->join('users as u', 'u.id', '=', 'sv.user_id')
                    ->whereBetween('sv.created_at', [$ini, $fin])
                    ->select(
                        'sv.id',
                        'sv.points_swap',
                        'u.tipo_usuario',
                        DB::raw("DATE_FORMAT(sv.created_at, '%m') as mes"),
                        DB::raw('MIN(cdp.tipo_producto) as tipo_producto')
                    )
                    ->groupBy('sv.id', 'sv.points_swap', 'u.tipo_usuario', DB::raw("DATE_FORMAT(sv.created_at, '%m')"))
                    ->get();
            };

            $resumir = function ($rows) {
                $resumen = [
                    'fisico'         => ['total_canjes' => 0, 'total_puntos' => 0],
                    'digital'        => ['total_canjes' => 0, 'total_puntos' => 0],
                    'sin_clasificar' => ['total_canjes' => 0, 'total_puntos' => 0],
                ];
                $porUsuario = [];

                foreach ($rows as $row) {
                    $tipo = in_array($row->tipo_producto, ['fisico', 'digital'])
                        ? $row->tipo_producto
                        : 'sin_clasificar';

                    $resumen[$tipo]['total_canjes']++;
                    $resumen[$tipo]['total_puntos'] += (int) $row->points_swap;

                    $tu = $row->tipo_usuario;
                    if (!isset($porUsuario[$tu])) {
                        $porUsuario[$tu] = ['total_canjes' => 0, 'total_puntos' => 0];
                    }
                    $porUsuario[$tu]['total_canjes']++;
                    $porUsuario[$tu]['total_puntos'] += (int) $row->points_swap;
                }

                $tipoMap = [1 => 'empleado_mabe', 2 => 'institucional', 3 => 'operario'];
                $usuariosFinal = [];
                foreach ($tipoMap as $tipo => $nombre) {
                    $usuariosFinal[] = [
                        'tipo'         => $tipo,
                        'nombre'       => $nombre,
                        'total_canjes' => $porUsuario[$tipo]['total_canjes'] ?? 0,
                        'total_puntos' => $porUsuario[$tipo]['total_puntos'] ?? 0,
                    ];
                }

                return [
                    'resumen'     => $resumen,
                    'por_usuario' => $usuariosFinal,
                ];
            };

            $resumirPorMes = function ($rows) {
                $porMes = [];

                foreach ($rows as $row) {
                    $mes  = $row->mes;
                    $tipo = in_array($row->tipo_producto, ['fisico', 'digital'])
                        ? $row->tipo_producto
                        : 'sin_clasificar';

                    if (!isset($porMes[$mes])) {
                        $porMes[$mes] = [
                            'fisico'         => ['total_canjes' => 0, 'total_puntos' => 0],
                            'digital'        => ['total_canjes' => 0, 'total_puntos' => 0],
                            'sin_clasificar' => ['total_canjes' => 0, 'total_puntos' => 0],
                        ];
                    }

                    $porMes[$mes][$tipo]['total_canjes']++;
                    $porMes[$mes][$tipo]['total_puntos'] += (int) $row->points_swap;
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

            $comparativa = $todosLosMeses->map(function ($mes) use ($meses1, $meses2) {
                $d1 = $meses1[$mes] ?? null;
                $d2 = $meses2[$mes] ?? null;

                $sumar       = fn($data) => $data
                    ? $data['fisico']['total_puntos'] + $data['digital']['total_puntos'] + $data['sin_clasificar']['total_puntos']
                    : 0;
                $sumarCanjes = fn($data) => $data
                    ? $data['fisico']['total_canjes'] + $data['digital']['total_canjes'] + $data['sin_clasificar']['total_canjes']
                    : 0;

                return [
                    'mes'      => $mes,
                    'periodo1' => $d1 ? [
                        'total_puntos'          => $sumar($d1),
                        'total_canjes'          => $sumarCanjes($d1),
                        'fisico_puntos'         => $d1['fisico']['total_puntos'],
                        'digital_puntos'        => $d1['digital']['total_puntos'],
                        'sin_clasificar_puntos' => $d1['sin_clasificar']['total_puntos'],
                    ] : null,
                    'periodo2' => $d2 ? [
                        'total_puntos'          => $sumar($d2),
                        'total_canjes'          => $sumarCanjes($d2),
                        'fisico_puntos'         => $d2['fisico']['total_puntos'],
                        'digital_puntos'        => $d2['digital']['total_puntos'],
                        'sin_clasificar_puntos' => $d2['sin_clasificar']['total_puntos'],
                    ] : null,
                ];
            })->values();

            return $this->sendResponse([
                'periodo1' => [
                    'fecha_inicio' => $p1Inicio,
                    'fecha_fin'    => $p1Fin,
                    'resumen'      => $p1['resumen'],
                    'por_usuario'  => $p1['por_usuario'],
                ],
                'periodo2' => [
                    'fecha_inicio' => $p2Inicio,
                    'fecha_fin'    => $p2Fin,
                    'resumen'      => $p2['resumen'],
                    'por_usuario'  => $p2['por_usuario'],
                ],
                'comparativa' => $comparativa,
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener estadísticas comparativas', $th->getMessage(), 500);
        }
    }

    private function getEstadisticasComparativaClubBohn(string $p1Inicio, string $p1Fin, string $p2Inicio, string $p2Fin): array
    {
        [$catalogoPorSku, $catalogoPorDescTalla] = $this->buildCatalogoLookups();

        $queryPeriodo = function (string $inicio, string $fin) use ($catalogoPorSku, $catalogoPorDescTalla) {
            $ini = \Carbon\Carbon::parse($inicio)->startOfDay();
            $fin = \Carbon\Carbon::parse($fin)->endOfDay();
            if ($ini->gt($fin)) [$ini, $fin] = [$fin, $ini];

            $rows = DB::connection('mysql_club_bohn')
                ->table('swaps_view as sv')
                ->join('users as u', 'u.id', '=', 'sv.user_id')
                ->whereBetween('sv.created_at', [$ini, $fin])
                ->select(
                    'sv.id',
                    'sv.points_swap',
                    'sv.sku',
                    'sv.desc',
                    'sv.size',
                    'u.tipo_usuario',
                    DB::raw("DATE_FORMAT(sv.created_at, '%m') as mes")
                )
                ->get();

            foreach ($rows as $row) {
                $producto = $this->matchProducto($catalogoPorSku, $catalogoPorDescTalla, $row->sku, $row->desc, $row->size);
                $row->tipo_producto = $producto->tipo_producto ?? null;
            }

            return $rows;
        };

        $resumir = function ($rows) {
            $resumen = [
                'fisico'         => ['total_canjes' => 0, 'total_puntos' => 0],
                'digital'        => ['total_canjes' => 0, 'total_puntos' => 0],
                'sin_clasificar' => ['total_canjes' => 0, 'total_puntos' => 0],
            ];
            $porUsuario = [];

            foreach ($rows as $row) {
                $tipo = in_array($row->tipo_producto, ['fisico', 'digital']) ? $row->tipo_producto : 'sin_clasificar';

                $resumen[$tipo]['total_canjes']++;
                $resumen[$tipo]['total_puntos'] += (int) $row->points_swap;

                $tu = $row->tipo_usuario;
                if (!isset($porUsuario[$tu])) {
                    $porUsuario[$tu] = ['total_canjes' => 0, 'total_puntos' => 0];
                }
                $porUsuario[$tu]['total_canjes']++;
                $porUsuario[$tu]['total_puntos'] += (int) $row->points_swap;
            }

            // Mapa propio de club_bohn: tipos 4, 5, 6
            $tipoMap = [4 => 'oso_polar', 5 => 'leon_marino', 6 => 'pinguino'];
            $usuariosFinal = [];
            foreach ($tipoMap as $tipo => $nombre) {
                $usuariosFinal[] = [
                    'tipo'         => $tipo,
                    'nombre'       => $nombre,
                    'total_canjes' => $porUsuario[$tipo]['total_canjes'] ?? 0,
                    'total_puntos' => $porUsuario[$tipo]['total_puntos'] ?? 0,
                ];
            }

            return ['resumen' => $resumen, 'por_usuario' => $usuariosFinal];
        };

        $resumirPorMes = function ($rows) {
            $porMes = [];
            foreach ($rows as $row) {
                $mes  = $row->mes;
                $tipo = in_array($row->tipo_producto, ['fisico', 'digital']) ? $row->tipo_producto : 'sin_clasificar';

                if (!isset($porMes[$mes])) {
                    $porMes[$mes] = [
                        'fisico'         => ['total_canjes' => 0, 'total_puntos' => 0],
                        'digital'        => ['total_canjes' => 0, 'total_puntos' => 0],
                        'sin_clasificar' => ['total_canjes' => 0, 'total_puntos' => 0],
                    ];
                }

                $porMes[$mes][$tipo]['total_canjes']++;
                $porMes[$mes][$tipo]['total_puntos'] += (int) $row->points_swap;
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

        $comparativa = $todosLosMeses->map(function ($mes) use ($meses1, $meses2) {
            $d1 = $meses1[$mes] ?? null;
            $d2 = $meses2[$mes] ?? null;

            $sumar       = fn($data) => $data ? $data['fisico']['total_puntos'] + $data['digital']['total_puntos'] + $data['sin_clasificar']['total_puntos'] : 0;
            $sumarCanjes = fn($data) => $data ? $data['fisico']['total_canjes'] + $data['digital']['total_canjes'] + $data['sin_clasificar']['total_canjes'] : 0;

            return [
                'mes'      => $mes,
                'periodo1' => $d1 ? [
                    'total_puntos'          => $sumar($d1),
                    'total_canjes'          => $sumarCanjes($d1),
                    'fisico_puntos'         => $d1['fisico']['total_puntos'],
                    'digital_puntos'        => $d1['digital']['total_puntos'],
                    'sin_clasificar_puntos' => $d1['sin_clasificar']['total_puntos'],
                ] : null,
                'periodo2' => $d2 ? [
                    'total_puntos'          => $sumar($d2),
                    'total_canjes'          => $sumarCanjes($d2),
                    'fisico_puntos'         => $d2['fisico']['total_puntos'],
                    'digital_puntos'        => $d2['digital']['total_puntos'],
                    'sin_clasificar_puntos' => $d2['sin_clasificar']['total_puntos'],
                ] : null,
            ];
        })->values();

        return [
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
        ];
    }

    private function resumenPorTipo($coleccion)
    {
        $totalPuntos = (int) $coleccion->sum('puntos_canjeados');
        $totalCanjes = $coleccion->count();

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
            'total_canjes' => $totalCanjes,
            'total_puntos_canjeados' => $totalPuntos,
            'top_categorias' => $porCategoria->take(10)->values(),
        ];
    }
    private function buildCatalogoLookups(bool $withCategoria = false): array
    {
        $catalogo = DB::table('dc_catalogo_productos as cdp')->select('cdp.*')->get();

        $categoriasPorId = [];
        if ($withCategoria) {
            $categoriasPorId = DB::connection('mysql_club_bohn')
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
                $key = mb_strtolower(trim($prod->nombre_producto)) . '|' . mb_strtolower(trim($prod->talla));
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
