<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\BitacoraEventos;
use App\Models\CatalogoCategoria;
use App\Models\CatalogoProductos;
use App\Models\CatalogoProveedores;
use App\Models\Plataformas;
use App\Models\VariablesGlobales;
use Carbon\Carbon;

class ProductosController extends BaseController
{
    public function crearProducto(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'nombre_producto' => 'required|string',
                'descripcion' => 'required|string',
                'marca' => 'required|string',
                'sku' => 'nullable|string',
                'color' => 'nullable|string',
                'talla' => 'nullable|string',
                'costo_con_iva' => 'required|integer',
                'costo_sin_iva' => 'required|integer',
                'costo_puntos_con_iva' => 'required|integer',
                'costo_puntos_sin_iva' => 'required|integer',
                'fee_brimagy' => 'required|integer',
                'subtotal' => 'required|integer',
                'envio_base' => 'required|integer',
                'costo_caja' => 'required|integer',
                'envio_extra' => 'required|integer',
                'total_envio' => 'nullable|integer',
                'total' => 'required|integer',
                'puntos' => 'required|integer',
                'factor' => 'required|integer',
                'tipo_registro' => 'required|string',
                'tipo_producto' => 'required|string',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $id_proveedor = $request->id_proveedor;
            $id_catalogo = $request->id_catalogo;
            $id_plataforma = $request->id_plataforma;

            if ($request->tipo_registro === 'excel') {
                // Buscar proveedor por nombre
                $proveedor = CatalogoProveedores::where('nombre', 'like', '%' . $request->proveedor . '%')->first();
                if (!$proveedor) {
                    DB::rollBack();
                    return $this->sendError('El proveedor "' . $request->proveedor . '" no existe', 'error', 404);
                }
                $id_proveedor = $proveedor->id;

                // Buscar categoría por nombre
                $catalogo = CatalogoCategoria::where('desc', 'like', '%' . $request->catalogo . '%')->first();
                if (!$catalogo) {
                    DB::rollBack();
                    return $this->sendError('La categoría "' . $request->catalogo . '" no existe', 'error', 404);
                }
                $id_catalogo = $catalogo->id;

                // Buscar plataforma por nombre
                $plataforma = Plataformas::where('nombre', '=', $request->nombre_plataforma)->first();
                if (!$plataforma) {
                    DB::rollBack();
                    return $this->sendError('La plataforma "' . $request->nombre_plataforma . '" no existe', 'error', 404);
                }
                $id_plataforma = $plataforma->id;
            }

            // Verificar si ya existe un producto con ese SKU
            $skuValido = !empty($request->sku) && strtoupper(trim($request->sku)) !== 'N/A';

            $productoExistente = $skuValido
                ? CatalogoProductos::where('sku', $request->sku)->first()
                : null;
            //$productoExistente = CatalogoProductos::where('sku', $request->sku)->first();

            // Verificar si la plataforma tiene variables globales registradas
            $variables = VariablesGlobales::where('id_plataforma', $id_plataforma)->first();

            if (!$variables) {
                $fee_brimagy = $request->fee_brimagy;
                $envio_base = $request->envio_base;
                $costo_caja = $request->costo_caja;
                $envio_extra = $request->envio_extra;
                $total_envio = $request->total_envio;
                $subtotal = $request->subtotal;
                $total = $request->total;
                $puntos = $request->puntos;
                $factor = $request->factor;
            } else {
                $fee_brimagy = $variables->fee_brimagy;
                $envio_base = $variables->envio_base;
                $costo_caja = $variables->costo_caja;
                $envio_extra = $variables->envio_extra;
                $porcentaje = (float) $fee_brimagy / 100;
                $valor_con_fee = round(
                    (float) $request->costo_puntos_sin_iva * $porcentaje
                );
                $subtotal = round($request->costo_puntos_sin_iva + $valor_con_fee);
                $total_envio = round($envio_base + $costo_caja + $envio_extra);
                $total = round($request->subtotal + $total_envio);
                $puntos = round($total + 1);
                $factor = round($puntos * 15);
                $factor = ($factor % 2 === 0) ? $factor + 1 : $factor;
            }

            if ($productoExistente) {
                // Guardar valores anteriores para la bitácora
                $valoresAnteriores = [
                    'fee_brimagy'          => $productoExistente->fee_brimagy,
                    'envio_base'           => $productoExistente->envio_base,
                    'costo_caja'           => $productoExistente->costo_caja,
                    'envio_extra'          => $productoExistente->envio_extra,
                    'subtotal'             => $productoExistente->subtotal,
                    'total_envio'          => $productoExistente->total_envio,
                    'total'                => $productoExistente->total,
                    'puntos'               => $productoExistente->puntos,
                    'factor'               => $productoExistente->factor,
                    'costo_con_iva'        => $productoExistente->costo_con_iva,
                    'costo_sin_iva'        => $productoExistente->costo_sin_iva,
                    'costo_puntos_con_iva' => $productoExistente->costo_puntos_con_iva,
                    'costo_puntos_sin_iva' => $productoExistente->costo_puntos_sin_iva,
                ];

                $fee_brimagy = $variables ? $variables->fee_brimagy : ($request->fee_brimagy ?? $productoExistente->fee_brimagy);
                $envio_base = $variables ? $variables->envio_base : ($request->envio_base ?? $productoExistente->envio_base);
                $costo_caja = $variables ? $variables->costo_caja : ($request->costo_caja ?? $productoExistente->costo_caja);
                $envio_extra = $variables ? $variables->envio_extra : ($request->envio_extra ?? $productoExistente->envio_extra);
                $costo_puntos_sin_iva = $request->costo_puntos_sin_iva ?? $productoExistente->costo_puntos_sin_iva;

                $porcentaje    = (float) $fee_brimagy / 100;
                $valor_con_fee = round((float) $costo_puntos_sin_iva * $porcentaje);
                $subtotal      = round($costo_puntos_sin_iva + $valor_con_fee);
                $total_envio   = round($envio_base + $costo_caja + $envio_extra);
                $total         = round($subtotal + $total_envio);
                $puntos        = round($total + 1);
                $factor        = round($puntos * 15);
                $factor = ($factor % 2 === 0) ? $factor + 1 : $factor;

                $valoresNuevos = [
                    'fee_brimagy'          => $fee_brimagy,
                    'envio_base'           => $envio_base,
                    'costo_caja'           => $costo_caja,
                    'envio_extra'          => $envio_extra,
                    'subtotal'             => $subtotal,
                    'total_envio'          => $total_envio,
                    'total'                => $total,
                    'puntos'               => $puntos,
                    'factor'               => $factor,
                    'costo_con_iva'        => $request->costo_con_iva,
                    'costo_sin_iva'        => $request->costo_sin_iva,
                    'costo_puntos_con_iva' => $request->costo_puntos_con_iva,
                    'costo_puntos_sin_iva' => $request->costo_puntos_sin_iva,
                ];

                // Detectar qué campos cambiaron
                $cambios = [];
                foreach ($valoresNuevos as $campo => $valorNuevo) {
                    $valorAnterior = $valoresAnteriores[$campo];
                    if ((string) $valorAnterior !== (string) $valorNuevo) {
                        $cambios[] = "{$campo}: {$valorAnterior} → {$valorNuevo}";
                    }
                }

                $productoExistente->update([
                    'nombre_producto'      => $request->nombre_producto,
                    'descripcion'          => $request->descripcion,
                    'marca'                => $request->marca,
                    'color'                => $request->color,
                    'talla'                => $request->talla,
                    'id_proveedor'         => $id_proveedor,
                    'id_catalogo'          => $id_catalogo,
                    'costo_con_iva'        => $request->costo_con_iva,
                    'costo_sin_iva'        => $request->costo_sin_iva,
                    'costo_puntos_con_iva' => $request->costo_puntos_con_iva,
                    'costo_puntos_sin_iva' => $request->costo_puntos_sin_iva,
                    'fee_brimagy'          => $fee_brimagy,
                    'subtotal'             => $subtotal,
                    'envio_base'           => $envio_base,
                    'costo_caja'           => $costo_caja,
                    'envio_extra'          => $envio_extra,
                    'total_envio'          => $total_envio,
                    'total'                => $total,
                    'puntos'               => $puntos,
                    'factor'               => $factor,
                    'id_plataforma'        => $id_plataforma,
                    'tipo_producto'        => $request->tipo_producto,
                    'updated_at'           => now()->setTimezone('America/Mexico_City'),
                ]);

                $user = Auth::user();
                $descripcionCambios = !empty($cambios)
                    ? implode(' | ', $cambios)
                    : "Sin cambios en valores numéricos";

                BitacoraEventos::create([
                    'evento'      => 'Edición de producto',
                    'tabla'      => 'dc_catalogo_productos',
                    'id_referencia' => $request->id_producto,
                    'descripcion' => "Se editó la siguiente información del producto: {$descripcionCambios}",
                    'id_usuario'  => $user->id,
                ]);

                DB::commit();
                return $this->sendResponse($productoExistente, 'Producto actualizado exitosamente.');
            }

            $producto = CatalogoProductos::create([
                'nombre_producto' => $request->nombre_producto,
                'descripcion' => $request->descripcion,
                'marca' => $request->marca,
                'sku' => $request->sku,
                'color' => $request->color,
                'talla' => $request->talla,
                'id_proveedor' => $id_proveedor,
                'id_catalogo' => $id_catalogo,
                'costo_con_iva' => $request->costo_con_iva,
                'costo_sin_iva' => $request->costo_sin_iva,
                'costo_puntos_con_iva' => $request->costo_puntos_con_iva,
                'costo_puntos_sin_iva' => $request->costo_puntos_sin_iva,
                'fee_brimagy' => $fee_brimagy,
                'subtotal' => $subtotal,
                'envio_base' => $envio_base,
                'costo_caja' => $costo_caja,
                'envio_extra' => $envio_extra,
                'total_envio' => $total_envio,
                'total' => $total,
                'puntos' => $puntos,
                'factor' => $factor,
                'id_plataforma' => $id_plataforma,
                'tipo_producto' => $request->tipo_producto,
            ]);

            $user = Auth::user();
            $log['evento'] = 'Creación de producto';
            $log['descripcion'] = "El usuario con id: {$user->id} añadio el producto con id: {$producto->id} al catalogo";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse($producto, 'Producto registrado exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al registrar el producto', $th->getMessage(), 500);
        }
    }

    public function verificarSkus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'skus' => 'required|array',
                'skus.*' => 'string'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Formato de datos no válido', $validator->errors());
            }

            $skusValidos = array_filter($request->skus, function ($sku) {
                return !empty($sku) && strtoupper(trim($sku)) !== 'N/A';
            });

            $skusExistentes = [];
            if (!empty($skusValidos)) {
                $skusExistentes = CatalogoProductos::whereIn('sku', $skusValidos)
                    ->pluck('sku')
                    ->toArray();
            }

            return $this->sendResponse([
                'skus_existentes' => $skusExistentes
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al verificar SKUs', $th->getMessage(), 500);
        }
    }

    public function verificarSkuDisponible(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'sku' => 'required|string'
            ]);

            if ($validator->fails()) {
                return $this->sendError('SKU es requerido', $validator->errors());
            }

            $productoExistente = CatalogoProductos::where('sku', $request->sku)->first();

            return $this->sendResponse([
                'disponible' => !$productoExistente,
                'producto_existente' => $productoExistente ? [
                    'id' => $productoExistente->id,
                    'nombre' => $productoExistente->nombre_producto
                ] : null
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al verificar SKU', $th->getMessage(), 500);
        }
    }

    public function getCatalogoProductos(Request $request)
    {
        try {
            $query = DB::table('dc_catalogo_productos as cpt')
                ->select(
                    'cpt.id',
                    'cpt.nombre_producto',
                    'cpt.descripcion',
                    'cpt.marca',
                    'cpt.sku',
                    'cpt.color',
                    'cpt.talla',
                    'cpv.nombre as proveedor',
                    'ac.desc as catalogo',
                    'cpt.costo_con_iva',
                    'cpt.costo_sin_iva',
                    'cpt.costo_puntos_con_iva',
                    'cpt.costo_puntos_sin_iva',
                    'cpt.fee_brimagy',
                    'cpt.subtotal',
                    'cpt.envio_base',
                    'cpt.costo_caja',
                    'cpt.envio_extra',
                    'cpt.total_envio',
                    'cpt.total',
                    'cpt.puntos',
                    'cpt.factor',
                    'cpt.tipo_producto',
                    'p.nombre as nombre_plataforma',
                    'cpt.created_at as fecha_creacion',
                )
                ->leftJoin('awards_categories as ac', 'cpt.id_catalogo', '=', 'ac.id')
                ->leftJoin('dc_plataformas as p', 'cpt.id_plataforma', '=', 'p.id')
                ->leftJoin('dc_catalogo_proveedores as cpv', 'cpt.id_proveedor', '=', 'cpv.id');

            // BÚSQUEDA
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('cpt.nombre_producto', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.descripcion', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.marca', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.sku', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.color', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.talla', 'LIKE', "%{$search}%")
                        ->orWhere('cpv.nombre', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.puntos', 'LIKE', "%{$search}%")
                        ->orWhere('ac.desc', 'LIKE', "%{$search}%");
                });
            }

            // BÚSQUEDA POR FECHAS
            if (
                $request->has('fecha1') && !empty($request->fecha1) &&
                $request->has('fecha2') && !empty($request->fecha2)
            ) {

                $fecha1 = Carbon::parse($request->fecha1);
                $fecha2 = Carbon::parse($request->fecha2);

                if ($fecha1->lt($fecha2)) {
                    $inicio = $fecha1->copy()->startOfDay();
                    $fin    = $fecha2->copy()->endOfDay();
                } else {
                    $inicio = $fecha2->copy()->startOfDay();
                    $fin    = $fecha1->copy()->endOfDay();
                }

                $query->whereBetween('cpt.created_at', [$inicio, $fin]);
            }

            $productos = $query->orderBy('cpt.created_at', 'desc')->get();

            return $this->sendResponse($productos);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los productos', $th, 500);
        }
    }

    public function getCatalogoProductosFisicos(Request $request)
    {
        try {
            $query = DB::table('dc_catalogo_productos as cpt')
                ->select(
                    'cpt.id',
                    'cpt.nombre_producto',
                    'cpt.descripcion',
                    'cpt.marca',
                    'cpt.sku',
                    'cpt.color',
                    'cpt.talla',
                    'cpv.nombre as proveedor',
                    'ac.desc as catalogo',
                    'cpt.costo_con_iva',
                    'cpt.costo_sin_iva',
                    'cpt.costo_puntos_con_iva',
                    'cpt.costo_puntos_sin_iva',
                    'cpt.fee_brimagy',
                    'cpt.subtotal',
                    'cpt.envio_base',
                    'cpt.costo_caja',
                    'cpt.envio_extra',
                    'cpt.total_envio',
                    'cpt.total',
                    'cpt.puntos',
                    'cpt.factor',
                    'cpt.tipo_producto',
                    'p.nombre as nombre_plataforma',
                    'cpt.created_at as fecha_creacion',
                )
                ->leftJoin('awards_categories as ac', 'cpt.id_catalogo', '=', 'ac.id')
                ->leftJoin('dc_plataformas as p', 'cpt.id_plataforma', '=', 'p.id')
                ->leftJoin('dc_catalogo_proveedores as cpv', 'cpt.id_proveedor', '=', 'cpv.id')
                ->where('cpt.tipo_producto', '=', 'fisico');

            // BÚSQUEDA
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('cpt.nombre_producto', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.descripcion', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.marca', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.sku', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.color', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.talla', 'LIKE', "%{$search}%")
                        ->orWhere('cpv.nombre', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.puntos', 'LIKE', "%{$search}%")
                        ->orWhere('ac.desc', 'LIKE', "%{$search}%");
                });
            }
            // BÚSQUEDA POR FECHAS
            if (($request->has('fecha1') && !empty($request->fecha1)) && ($request->has('fecha2') && !empty($request->fecha2))) {
                $fecha1 = Carbon::parse($request->fecha1)->startOfDay();
                $fecha2 = Carbon::parse($request->fecha2)->endOfDay();
                // Ordenar para que siempre la menor sea el inicio
                $inicio = $fecha1->lt($fecha2) ? $fecha1->startOfDay() : $fecha2->startOfDay();
                $fin    = $fecha1->lt($fecha2) ? $fecha2->endOfDay()   : $fecha1->endOfDay();

                $query->whereBetween('cpt.created_at', [$inicio, $fin]);
            }

            $productos = $query->orderBy('cpt.created_at', 'desc')->get();

            return $this->sendResponse($productos);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los productos', $th, 500);
        }
    }

    public function getCatalogoProductosDigitales(Request $request)
    {
        try {
            $query = DB::table('dc_catalogo_productos as cpt')
                ->select(
                    'cpt.id',
                    'cpt.nombre_producto',
                    'cpt.descripcion',
                    'cpt.marca',
                    'cpt.sku',
                    'cpt.color',
                    'cpt.talla',
                    'cpv.nombre as proveedor',
                    'ac.desc as catalogo',
                    'cpt.costo_con_iva',
                    'cpt.costo_sin_iva',
                    'cpt.costo_puntos_con_iva',
                    'cpt.costo_puntos_sin_iva',
                    'cpt.fee_brimagy',
                    'cpt.subtotal',
                    'cpt.envio_base',
                    'cpt.costo_caja',
                    'cpt.envio_extra',
                    'cpt.total_envio',
                    'cpt.total',
                    'cpt.puntos',
                    'cpt.factor',
                    'cpt.tipo_producto',
                    'p.nombre as nombre_plataforma',
                    'cpt.created_at as fecha_creacion',
                )
                ->leftJoin('awards_categories as ac', 'cpt.id_catalogo', '=', 'ac.id')
                ->leftJoin('dc_plataformas as p', 'cpt.id_plataforma', '=', 'p.id')
                ->leftJoin('dc_catalogo_proveedores as cpv', 'cpt.id_proveedor', '=', 'cpv.id')
                ->where('cpt.tipo_producto', '=', 'digital');

            // BÚSQUEDA
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('cpt.nombre_producto', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.descripcion', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.marca', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.sku', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.color', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.talla', 'LIKE', "%{$search}%")
                        ->orWhere('cpv.nombre', 'LIKE', "%{$search}%")
                        ->orWhere('cpt.puntos', 'LIKE', "%{$search}%")
                        ->orWhere('ac.desc', 'LIKE', "%{$search}%");
                });
            }
            // BÚSQUEDA POR FECHAS
            if (($request->has('fecha1') && !empty($request->fecha1)) && ($request->has('fecha2') && !empty($request->fecha2))) {
                $fecha1 = Carbon::parse($request->fecha1)->startOfDay();
                $fecha2 = Carbon::parse($request->fecha2)->endOfDay();
                // Ordenar para que siempre la menor sea el inicio
                $inicio = $fecha1->lt($fecha2) ? $fecha1->startOfDay() : $fecha2->startOfDay();
                $fin    = $fecha1->lt($fecha2) ? $fecha2->endOfDay()   : $fecha1->endOfDay();

                $query->whereBetween('cpt.created_at', [$inicio, $fin]);
            }

            $productos = $query->orderBy('cpt.created_at', 'desc')->get();

            return $this->sendResponse($productos);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los productos', $th, 500);
        }
    }

    public function editarProducto(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_producto' => 'required|integer|exists:dc_catalogo_productos,id'
            ]);
            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('Falta el id del producto.', $validator->errors());
            }

            $producto = CatalogoProductos::find($request->id_producto);
            if (!$producto) {
                DB::rollBack();
                return $this->sendError('Este producto no existe', [], 404);
            }

            // Guardar valores anteriores para comparar
            $camposAuditables = [
                'fee_brimagy',
                'envio_base',
                'costo_caja',
                'envio_extra',
                'subtotal',
                'total_envio',
                'total',
                'puntos',
                'factor',
                'costo_con_iva',
                'costo_sin_iva',
                'costo_puntos_con_iva',
                'costo_puntos_sin_iva',
            ];
            $valoresAnteriores = $producto->only($camposAuditables);

            // Preparar datos a actualizar
            $datosParaActualizar = $request->only([
                'nombre_producto',
                'descripcion',
                'marca',
                'sku',
                'color',
                'talla',
                'id_proveedor',
                'id_catalogo',
                'costo_con_iva',
                'costo_sin_iva',
                'costo_puntos_con_iva',
                'costo_puntos_sin_iva',
                'fee_brimagy',
                'subtotal',
                'envio_base',
                'costo_caja',
                'envio_extra',
                'total_envio',
                'total',
                'puntos',
                'factor',
            ]);

            $datosParaActualizar = array_filter($datosParaActualizar, function ($value) {
                return !is_null($value) && $value !== '';
            });
            $datosParaActualizar = array_filter($datosParaActualizar, function ($value, $campo) use ($producto) {
                return (string) $producto->$campo !== (string) $value;
            }, ARRAY_FILTER_USE_BOTH);

            if (empty($datosParaActualizar)) {
                DB::rollBack();
                return $this->sendResponse("No hay cambios que guardar.");
            }

            // Recalcular si alguno de los campos clave fue enviado
            $camposQueRecalculan = ['fee_brimagy', 'envio_base', 'costo_caja', 'envio_extra', 'costo_puntos_sin_iva'];
            $debeRecalcular = count(array_intersect($camposQueRecalculan, array_keys($datosParaActualizar))) > 0;

            if ($debeRecalcular) {
                // Mezclar valores actuales con los nuevos para tener todos los datos
                $fee_brimagy  = $datosParaActualizar['fee_brimagy'] ?? $producto->fee_brimagy;
                $envio_base   = $datosParaActualizar['envio_base'] ?? $producto->envio_base;
                $costo_caja   = $datosParaActualizar['costo_caja'] ?? $producto->costo_caja;
                $envio_extra  = $datosParaActualizar['envio_extra'] ?? $producto->envio_extra;
                $costo_puntos_sin_iva = $datosParaActualizar['costo_puntos_sin_iva'] ?? $producto->costo_puntos_sin_iva;

                $porcentaje    = (float) $fee_brimagy / 100;
                $valor_con_fee = round((float) $costo_puntos_sin_iva * $porcentaje);
                $subtotal      = round($costo_puntos_sin_iva + $valor_con_fee);
                $total_envio   = round($envio_base + $costo_caja + $envio_extra);
                $total         = round($subtotal + $total_envio);
                $puntos        = round($total + 1);
                $factor        = round($puntos * 15);
                $factor = ($factor % 2 === 0) ? $factor + 1 : $factor;

                // Sobreescribir con los recalculados
                $datosParaActualizar['subtotal']    = $subtotal;
                $datosParaActualizar['total_envio'] = $total_envio;
                $datosParaActualizar['total']       = $total;
                $datosParaActualizar['puntos']      = $puntos;
                $datosParaActualizar['factor']      = $factor;
            }

            $datosParaActualizar['updated_at'] = now()->setTimezone('America/Mexico_City');

            $producto->update($datosParaActualizar);

            // Detectar cambios para la bitácora
            $cambios = [];
            foreach ($camposAuditables as $campo) {
                $anterior = $valoresAnteriores[$campo] ?? null;
                $nuevo    = $datosParaActualizar[$campo] ?? null;
                if ($nuevo !== null && (string) $anterior !== (string) $nuevo) {
                    $cambios[] = "{$campo}: {$anterior} → {$nuevo}";
                }
            }

            $descripcionCambios = !empty($cambios)
                ? implode(' | ', $cambios)
                : "Solo se editaron campos de texto";

            $userLog = Auth::user();
            BitacoraEventos::create([
                'evento' => 'Edición de producto',
                'tabla'      => 'dc_catalogo_productos',
                'id_referencia' => $request->id_producto,
                'descripcion' => "Se editó la siguiente información del producto: {$descripcionCambios}",
                'id_usuario' => $userLog->id,
            ]);

            DB::commit();
            return $this->sendResponse("Se ha actualizado el producto con éxito");
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al actualizar el producto', $th, 500);
        }
    }
    public function eliminarProducto(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_producto' => 'required|integer|exists:dc_catalogo_productos,id'
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('Falta el id del producto.', $validator->errors());
            }

            $producto = CatalogoProductos::find($request->id_producto);

            if (!$producto) {
                DB::rollBack();
                return $this->sendError('Este producto no existe', [], 404);
            }

            // Eliminar el producto
            $producto->delete();

            $user = Auth::user();
            $log['evento'] = 'Eliminación de producto';
            $log['descripcion'] = "El usuario con id: {$user->id} eliminó un producto";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse('Producto eliminado exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al eliminar el producto', $th->getMessage(), 500);
        }
    }

    public function busquedaInteligenteBrimagy(Request $request)
    {
        try {
            $query = DB::table('dc_catalogo_productos as cpt')
                ->select(
                    'cpt.id',
                    'cpt.nombre_producto',
                    'cpt.descripcion',
                    'cpt.marca',
                    'cpt.sku',
                    'cpt.color',
                    'cpt.talla',
                    'cpv.nombre as proveedor',
                    'ac.desc as catalogo',
                    'cpt.costo_con_iva',
                    'cpt.costo_sin_iva',
                    'cpt.costo_puntos_con_iva',
                    'cpt.costo_puntos_sin_iva',
                    'cpt.fee_brimagy',
                    'cpt.subtotal',
                    'cpt.envio_base',
                    'cpt.costo_caja',
                    'cpt.envio_extra',
                    'cpt.total_envio',
                    'cpt.total',
                    'cpt.puntos',
                    'cpt.factor',
                    'cpt.created_at as fecha_creacion',
                )
                ->leftJoin('awards_categories as ac', 'cpt.id_catalogo', '=', 'ac.id')
                ->leftJoin('dc_catalogo_proveedores as cpv', 'cpt.id_proveedor', '=', 'cpv.id');

            // BÚSQUEDA POR PUNTOS CON RANGO
            if ($request->has('puntos') && !empty($request->puntos)) {
                $puntos = (int) $request->puntos;
                $rangoMinimo = $puntos - 200;
                $rangoMaximo = $puntos + 200;

                $query->whereBetween('cpt.puntos', [$rangoMinimo, $rangoMaximo]);
            }

            // BÚSQUEDA POR CATEGORÍA
            if ($request->has('categoria') && !empty($request->categoria)) {
                $categoria = $request->categoria;
                $query->where('ac.id', '=', $categoria);
            }

            // ORDENAR POR TOTAL DE MANERA ASCENDENTE (menor a mayor)
            //$productos = $query->orderBy('cpt.total', 'asc')->get();

            $productos = $query->orderBy('cpt.created_at', 'desc')->get();

            return $this->sendResponse($productos);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los productos', $th, 500);
        }
    }
    public function getBitacoraProductoPorId(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_producto' => 'required|integer|exists:dc_catalogo_productos,id'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Falta el id del producto.', $validator->errors());
            }

            $query = DB::table('dc_catalogo_productos as cdp')
                ->select(
                    'u.name as nombre_usuario',
                    'u.email as correo_usuario',
                    'be.evento',
                    'be.descripcion',
                    'be.created_at as fecha_edicion',
                )
                ->leftJoin('bitacora_eventos as be', 'cdp.id', '=', 'be.id_referencia')
                ->leftJoin('users as u', 'be.id_usuario', '=', 'u.id')
                ->where('be.id_referencia', $request->id_producto);

            $productos = $query->orderBy('be.created_at', 'desc')->get();

            return $this->sendResponse($productos);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los productos', $th, 500);
        }
    }
}
