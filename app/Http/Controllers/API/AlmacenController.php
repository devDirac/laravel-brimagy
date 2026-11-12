<?php

namespace App\Http\Controllers\API;

use App\Services\FolioCanjeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\BitacoraEventos;
use App\Models\CatalogoProductos;
use App\Models\CatalogoProveedores;
use App\Models\Plataformas;
use App\Models\ProductoBrimagy;
use App\Models\ProductoClub;
use App\Models\ProductoLocal;
use App\Models\RecepcionAlmacen;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AlmacenController extends BaseController
{
    public function __construct(private FolioCanjeResolver $folioCanjeResolver) {}
    public function getProductosAlmacen(Request $request)
    {
        try {
            $query = DB::table('dc_recepcion_almacen as ra')
                ->select(
                    'ra.id',
                    'ra.id_canje',
                    'ra.id_usuario',
                    'ra.id_producto',
                    'cdp.nombre_producto',
                    'cdp.marca',
                    'cdp.sku',
                    'cdp.id_plataforma',
                    'ra.id_proveedor',
                    'cp.nombre as nombre_proveedor',
                    'ra.id_orden_compra',
                    'oc.no_orden',
                    'ra.cantidad_producto',
                    DB::raw('(
                    SELECT COALESCE(SUM(ra2.cantidad_almacen), 0)
                    FROM dc_recepcion_almacen ra2
                    WHERE ra2.id_canje = ra.id_canje
                      AND ra2.id_producto = ra.id_producto
                ) as cantidad_almacen'),
                    'ra.fecha_compra',
                    'ra.costo_envio_real',
                    'ra.comentarios',
                    'ra.estatus',
                    'ra.guia',
                    DB::raw('(
                    SELECT GROUP_CONCAT(CASE WHEN f.tipo_archivo = "pdf" THEN f.nombre_factura END)
                    FROM dc_facturas f
                    WHERE f.id_orden_compra = ra.id_orden_compra
                ) as nombre_factura'),
                    DB::raw('(
                    SELECT GROUP_CONCAT(CASE WHEN f.tipo_archivo = "pdf" THEN f.url_factura END)
                    FROM dc_facturas f
                    WHERE f.id_orden_compra = ra.id_orden_compra
                ) as url_factura'),
                    DB::raw('(
                    SELECT COUNT(CASE WHEN f.tipo_archivo = "pdf" THEN 1 END)
                    FROM dc_facturas f
                    WHERE f.id_orden_compra = ra.id_orden_compra
                ) as tiene_factura')
                )
                ->leftJoin('dc_catalogo_productos as cdp', 'ra.id_producto', '=', 'cdp.id')
                ->leftJoin(
                    'dc_catalogo_proveedores as cp',
                    'cp.id',
                    '=',
                    DB::raw('COALESCE(ra.id_proveedor, cdp.id_proveedor)')
                )
                ->leftJoin('dc_orden_compra as oc', 'ra.id_orden_compra', '=', 'oc.id')
                ->whereIn('ra.id', function ($sub) {
                    $sub->selectRaw('MIN(id)')
                        ->from('dc_recepcion_almacen')
                        ->whereNotNull('id_canje')
                        ->groupBy('id_canje', 'id_producto');
                })
                ->where(function ($q) {
                    $q->whereNull('ra.estatus')
                        ->orWhere('ra.estatus', '!=', 'entregado');
                })
                ->whereNotNull('cdp.id_plataforma');

            $productosAlmacen = $this->enriquecerCanjes(
                $query->orderBy('ra.created_at', 'desc')->get()
            );

            return $this->sendResponse($productosAlmacen);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los productos en almacen', $th->getMessage(), 500);
        }
    }
    public function getProductoAlmacenPorId(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_producto_almacen' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            // Registro sobre el que se hizo clic
            $registro = DB::table('dc_recepcion_almacen')
                ->where('id', $request->id_producto_almacen)
                ->first();

            if (!$registro) {
                return $this->sendError('Producto de almacén no encontrado', null, 404);
            }

            $productos = DB::table('dc_recepcion_almacen as ra')
                ->select(
                    'ra.id as id_producto_almacen',
                    'ra.id_canje',
                    'ra.id_usuario',
                    'up.name as nombre_usuario_registro',
                    'ra.id_producto',
                    'cp.nombre as nombre_proveedor',
                    'ra.id_proveedor',
                    'cdp.nombre_producto',
                    'cdp.marca',
                    'cdp.sku',
                    'cdp.costo_con_iva',
                    'cdp.costo_sin_iva',
                    'cdp.id_plataforma',
                    'cdp2.nombre_producto as nombre_producto_nuevo',
                    'cdp2.marca as marca_nuevo',
                    'cdp2.sku as sku_nuevo',
                    'ra.precio_compra',
                    'ra.id_orden_compra',
                    'oc.no_orden',
                    'ra.cantidad_producto',
                    'ra.cantidad_almacen',
                    'ra.fecha_compra',
                    'ra.costo_envio_real',
                    'ra.folio_factura',
                    'ra.comentarios',
                    'ra.estatus',
                    'ra.guia',
                    'ra.imei',
                    'ra.no_serie',
                    'ea.evidencias',
                    'ra.tipo_envio',
                    DB::raw('COALESCE(ra.id_proveedor, cdp.id_proveedor) as id_proveedor_asignado'),
                    DB::raw('(
                    SELECT GROUP_CONCAT(CASE WHEN f.tipo_archivo = "pdf" THEN f.fecha_pago END)
                    FROM dc_facturas f
                    WHERE f.id_orden_compra = ra.id_orden_compra
                ) as fecha_pago'),
                )
                ->leftJoin('dc_catalogo_productos as cdp', 'ra.id_producto', '=', 'cdp.id')
                ->leftJoin('dc_catalogo_productos as cdp2', 'ra.id_producto_nuevo', '=', 'cdp2.id')
                ->leftJoin(
                    'dc_catalogo_proveedores as cp',
                    'cp.id',
                    '=',
                    DB::raw('COALESCE(ra.id_proveedor, cdp.id_proveedor)')
                )
                ->leftJoin('dc_orden_compra as oc', 'ra.id_orden_compra', '=', 'oc.id')
                ->leftJoin('dc_usuarios_plataforma as up', 'ra.id_usuario', '=', 'up.id')
                ->leftJoin('dc_evidencias_almacen as ea', 'ea.id_almacen_producto', '=', 'ra.id')
                ->where('ra.id_canje', $registro->id_canje)
                ->where('ra.id_producto', $registro->id_producto)
                ->get();

            // Plataforma, folio y datos del usuario del canje
            $base = $productos->sortBy('id_producto_almacen')->first();
            $base = $this->enriquecerCanjes(collect([$base]))->first();

            $resultado = [
                'id_producto_almacen' => $base->id_producto_almacen,
                'id_canje' => $base->id_canje,
                'id_usuario' => $base->id_usuario,
                'nombre_usuario_registro' => $base->nombre_usuario_registro,
                'id_producto' => $base->id_producto,
                'nombre_producto' => $base->nombre_producto,
                'nombre_proveedor' => $base->nombre_proveedor,
                'id_proveedor' => $base->id_proveedor_asignado,
                'marca' => $base->marca,
                'sku' => $base->sku,
                'costo_sin_iva' => $base->costo_sin_iva,
                'costo_con_iva' => $base->costo_con_iva,
                'id_orden_compra' => $base->id_orden_compra,
                'no_orden' => $base->no_orden,
                'cantidad_producto' => $base->cantidad_producto,
                'estatus' => $base->estatus,
                'costo_envio_real' => $base->costo_envio_real,
                'fecha_pago' => $base->fecha_pago,
                'evidencias' => $base->evidencias,
                'guia' => $base->guia,
                'tipo_envio' => $base->tipo_envio,
                'imei' => $base->imei,
                'no_serie' => $base->no_serie,
                'folio_factura' => $base->folio_factura,
                'comentarios' => $base->comentarios,

                // Plataforma y canje
                'plataforma' => $base->plataforma,
                'plataforma_nombre' => $base->plataforma_nombre,
                'folio_canje' => $base->folio_canje,

                // Usuario que realizó el canje
                'nombre_usuario' => $base->nombre_usuario,
                'email' => $base->email,
                'phone' => $base->phone,
                'calle' => $base->calle,
                'numero_calle' => $base->numero_calle,
                'numero_interior' => $base->numero_interior,
                'colonia' => $base->colonia,
                'municipio' => $base->municipio,
                'estado' => $base->estado,
                'codigo_postal' => $base->codigo_postal,
                'between_1' => $base->between_1,
                'between_2' => $base->between_2,
                'referencia_adicional' => $base->referencia_adicional,

                // Total sumado de todos los registros
                'cantidad_almacen' => $productos->sum('cantidad_almacen'),

                // Detalle de cada registro por separado
                'productos' => $productos
                    ->filter(fn($p) => $p->cantidad_almacen > 0)
                    ->map(fn($p) => [
                        'id_producto_almacen' => $p->id_producto_almacen,
                        'id_proveedor' => $base->id_proveedor_asignado !== null ? (int) $base->id_proveedor_asignado : null,
                        'nombre_proveedor' => $p->nombre_proveedor,
                        'nombre_producto_nuevo' => $p->nombre_producto_nuevo ?? $p->nombre_producto,
                        'marca_nuevo' => $p->marca_nuevo ?? $p->marca,
                        'sku_nuevo' => $p->sku_nuevo ?? $p->sku,
                        'precio_compra' => $p->precio_compra ?? $p->costo_con_iva,
                        'cantidad_almacen' => $p->cantidad_almacen,
                        'fecha_compra' => $p->fecha_compra,
                        'imei' => $p->imei,
                        'costo_envio_real' => $base->costo_envio_real,
                        'fecha_pago' => $base->fecha_pago,
                        'no_serie' => $p->no_serie,
                        'folio_factura' => $p->folio_factura,
                        'comentarios' => $p->comentarios,
                    ])->values()
            ];

            return $this->sendResponse($resultado);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener el producto en almacen', $th->getMessage(), 500);
        }
    }
    public function recibirProductoAlmacen(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_producto_almacen' => 'required|integer',
                'cantidad_producto' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $producto_almacen = RecepcionAlmacen::lockForUpdate()->find($request->id_producto_almacen);

            if (!$producto_almacen) {
                DB::rollBack();
                return $this->sendError('Este producto no se encuentra en almacen', 'error', 404);
            }

            // Registros de ESTE producto dentro del canje
            // (el original + los creados al recibir con nuevo precio)
            $registrosDelProducto = fn() => RecepcionAlmacen::where('id_canje', $producto_almacen->id_canje)
                ->where('id_producto', $producto_almacen->id_producto);

            $cantidadAlmacenActual = (int) $registrosDelProducto()->sum('cantidad_almacen');
            $nuevaCantidadAlmacen = $cantidadAlmacenActual + (int) $request->cantidad_producto;
            $cantidadTotal = (int) $producto_almacen->cantidad_producto;

            if ($nuevaCantidadAlmacen > $cantidadTotal) {
                DB::rollBack();
                return $this->sendError(
                    'La cantidad a recibir excede la cantidad total del pedido',
                    [
                        'cantidad_pendiente' => $cantidadTotal - $cantidadAlmacenActual,
                        'cantidad_recibida' => $cantidadAlmacenActual,
                        'cantidad_total' => $cantidadTotal,
                        'nuevaCantidadAlmacen' => $nuevaCantidadAlmacen,
                    ],
                    400
                );
            }

            $user = Auth::user();
            $estatus = $nuevaCantidadAlmacen >= $cantidadTotal
                ? 'en_almacen'
                : 'en_almacen_parcialmente';

            $producto_catalogo = CatalogoProductos::find($producto_almacen->id_producto);

            if (!$request->filled('precio_compra')) {
                $producto_almacen->update([
                    'id_proveedor' => $request->id_proveedor,
                    // se suma a lo ya recibido en este registro
                    'cantidad_almacen' => (int) $producto_almacen->cantidad_almacen + (int) $request->cantidad_producto,
                    'imei' => $request->imei,
                    'no_serie' => $request->no_serie,
                    'folio_factura' => $request->folio_factura,
                    'comentarios' => $request->comentarios,
                    'precio_compra' => $producto_almacen->precio_compra ?? $producto_catalogo?->costo_con_iva,
                    'fecha_compra' => $request->fecha_compra,
                ]);

                $producto_catalogo?->update([
                    'id_proveedor' => $request->id_proveedor,
                ]);

                $log = [
                    'evento' => 'Recepción de producto en almacén',
                    'descripcion' => "El usuario con id: {$user->id} recibió {$request->cantidad_producto} unidades del producto {$producto_almacen->id_producto} del canje {$producto_almacen->id_canje}.",
                    'id_usuario' => $user->id,
                ];
            } else {
                RecepcionAlmacen::create([
                    // datos que deben duplicarse
                    'id_canje' => $producto_almacen->id_canje,
                    'id_usuario' => $producto_almacen->id_usuario,
                    'id_producto' => $producto_almacen->id_producto,
                    'cantidad_producto' => $producto_almacen->cantidad_producto,
                    'id_orden_compra' => $producto_almacen->id_orden_compra,
                    'fecha' => $producto_almacen->fecha,
                    // nuevos datos
                    'id_producto_nuevo' => $producto_almacen->id_producto,
                    'precio_compra' => $request->precio_compra,
                    'fecha_compra' => $request->fecha_compra,
                    'id_proveedor' => $request->id_proveedor,
                    'cantidad_almacen' => $request->cantidad_producto,
                    'imei' => $request->imei,
                    'no_serie' => $request->no_serie,
                    'folio_factura' => $request->folio_factura,
                    'comentarios' => $request->comentarios,
                ]);

                $producto_catalogo?->update([
                    'costo_con_iva' => $request->precio_compra,
                    'costo_sin_iva' => $request->precio_compra / 1.16,
                    'id_proveedor' => $request->id_proveedor,
                ]);

                $log = [
                    'evento' => 'Recepción de producto en almacén con nuevo precio',
                    'descripcion' => "El usuario con id: {$user->id} recibió {$request->cantidad_producto} unidades del producto {$producto_almacen->id_producto} del canje {$producto_almacen->id_canje} con un nuevo precio de {$request->precio_compra}.",
                    'id_usuario' => $user->id,
                ];
            }

            // Solo se actualiza el estatus de ESTE producto, no de todo el canje
            $registrosDelProducto()->update(['estatus' => $estatus]);

            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse([
                'producto' => $producto_almacen->fresh(),
                'estatus' => $estatus,
                'cantidad_recibida' => (int) $request->cantidad_producto,
                'cantidadAlmacenActual' => $nuevaCantidadAlmacen,
                'cantidad_total' => $cantidadTotal,
                'cantidad_pendiente' => $cantidadTotal - $nuevaCantidadAlmacen,
            ], 'Producto recibido en almacén correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al recibir el producto', $th->getMessage(), 500);
        }
    }
    public function registrarNuevoPrecioAlmacen(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'precio_compra' => 'required|integer',
                'id_producto' => 'required|integer',
                'id_producto_almacen' => 'required|integer',
                'id_proveedor' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $datosProducto = CatalogoProductos::where('id', $request->id_producto)->first();

            if (!$datosProducto) {
                DB::rollBack();
                return $this->sendError('El producto no existe en el catálogo', 'error', 404);
            }

            //datos del producto existente
            $nombre_producto = $datosProducto->nombre_producto;
            $descripcion = $datosProducto->descripcion;
            $marca = $datosProducto->marca;
            $sku = $datosProducto->sku;
            $color = $datosProducto->color;
            $talla = $datosProducto->talla;
            $id_proveedor = $request->id_proveedor;
            $id_catalogo = $datosProducto->id_catalogo;
            $costo_con_iva = $request->precio_compra * 1.16;
            $costo_sin_iva = $request->precio_compra;
            $costo_puntos_con_iva = $datosProducto->costo_puntos_con_iva;
            $costo_puntos_sin_iva = $datosProducto->costo_puntos_sin_iva;
            $fee_brimagy = $datosProducto->fee_brimagy;
            $subtotal = $datosProducto->subtotal;
            $envio_base = $datosProducto->envio_base;
            $costo_caja = $datosProducto->costo_caja;
            $envio_extra = $datosProducto->envio_extra;
            $total_envio = $datosProducto->total_envio;
            $total = $datosProducto->total;
            $puntos = $datosProducto->puntos;
            $factor = $datosProducto->factor;
            $foto_producto = $datosProducto->foto_producto;
            $id_plataforma = $datosProducto->id_plataforma;
            $tipo_producto = $datosProducto->tipo_producto;

            // La plataforma se toma del producto, no de la vista
            $esClubBohn = Plataformas::where('id', $id_plataforma)->value('nombre') === 'club bohn';

            $datosProductoPlataforma = [
                'desc' => $nombre_producto,
                'features' => $descripcion,
                'required_score' => $puntos,
                'sub_category_id' => $id_catalogo,
                'photo_name' => $foto_producto,
                'sku' => $sku,
                'TyC' => "",
                'validity' => "",
            ];

            if ($esClubBohn) {
                $producto_brimagy = ProductoClub::create($datosProductoPlataforma);
            } else {
                $producto_brimagy = ProductoBrimagy::create($datosProductoPlataforma);
                ProductoLocal::create($datosProductoPlataforma);
            }

            $producto = CatalogoProductos::create([
                'nombre_producto' => $nombre_producto,
                'descripcion' => $descripcion,
                'marca' => $marca,
                'sku' => $sku,
                'color' => $color,
                'talla' => $talla,
                'id_proveedor' => $id_proveedor,
                'id_catalogo' => $id_catalogo,
                'costo_con_iva' => $costo_con_iva,
                'costo_sin_iva' => $costo_sin_iva,
                'costo_puntos_con_iva' => $costo_puntos_con_iva,
                'costo_puntos_sin_iva' => $costo_puntos_sin_iva,
                'fee_brimagy' => $fee_brimagy,
                'subtotal' => $subtotal,
                'envio_base' => $envio_base,
                'costo_caja' => $costo_caja,
                'envio_extra' => $envio_extra,
                'total_envio' => $total_envio,
                'total' => $total,
                'puntos' => $puntos,
                'factor' => $factor,
                'id_producto_brimagy' => $producto_brimagy->id,
                'id_plataforma' => $id_plataforma,
                'tipo_producto' => $tipo_producto,
            ]);

            $nombreProveedor = CatalogoProveedores::find($id_proveedor)->nombre;

            $datosParaActualizar = [
                'id_producto_nuevo' => $producto->id,
                'precio_compra' => $request->precio_compra,
                'id_proveedor' => $id_proveedor ?? null,
                'updated_at' => now()->setTimezone('America/Mexico_City'),
            ];

            $datosParaActualizar = array_filter($datosParaActualizar, function ($value) {
                return !is_null($value) && $value !== '';
            });

            $productoNuevoAlmacen = RecepcionAlmacen::where('id', $request->id_producto_almacen)
                ->update($datosParaActualizar);

            $user = Auth::user();
            $log['evento'] = 'Registro nuevo producto desde almacen';
            $log['descripcion'] = "El usuario con id: {$user->id} añadio el producto con id: {$producto->id} al almacen";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse([
                ...$producto->toArray(),
                'nombre_proveedor' => $nombreProveedor,
            ], 'Producto registrado exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al registrar el producto', $th->getMessage(), 500);
        }
    }
    public function addGuiaProductoAlmacen(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'guia_producto' => 'required|string',
                'tipo_envio' => 'required|integer',
                'id_producto_almacen' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $producto_almacen = RecepcionAlmacen::find($request->id_producto_almacen)->first();

            if (!$producto_almacen) {
                DB::rollBack();
                return $this->sendError('Este producto no se encuentra en almacen', 'error', 404);
            }

            RecepcionAlmacen::where('id_canje', $producto_almacen->id_canje)
                ->where('id_producto', $producto_almacen->id_producto)
                ->update([
                    'guia' => $request->guia_producto,
                    'tipo_envio' => $request->tipo_envio,
                    'estatus' => 'guia_asignada',
                ]);

            $user = Auth::user();
            BitacoraEventos::create([
                'evento' => 'Se añadió una guía a un producto en almacen',
                'descripcion' => "El usuario con id: {$user->id} añadió la guía {$request->guia_producto} al producto {$producto_almacen->id_producto} del canje {$producto_almacen->id_canje} en almacén",
                'id_usuario' => $user->id,
            ]);

            DB::commit();
            return $this->sendResponse('Guía añadida correctamente al producto.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al asignar una guía al producto', $th->getMessage(), 500);
        }
    }
    public function enviarProductoAlmacen(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_producto_almacen' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $producto_almacen = RecepcionAlmacen::find($request->id_producto_almacen);

            if (!$producto_almacen) {
                DB::rollBack();
                return $this->sendError('Este producto no se encuentra en almacen', 'error', 404);
            }

            // Solo los registros de ESTE producto dentro del canje
            RecepcionAlmacen::where('id_canje', $producto_almacen->id_canje)
                ->where('id_producto', $producto_almacen->id_producto)
                ->update(['estatus' => 'enviado']);

            $user = Auth::user();
            BitacoraEventos::create([
                'evento' => 'Se envió un producto de almacén',
                'descripcion' => "El usuario con id: {$user->id} envió el producto {$producto_almacen->id_producto} del canje {$producto_almacen->id_canje}",
                'id_usuario' => $user->id,
            ]);

            DB::commit();
            return $this->sendResponse('Producto enviado correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al enviar el producto', $th->getMessage(), 500);
        }
    }
    public function confirmarRecepcionProductoAlmacen(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_producto_almacen' => 'required|integer',
                'costo_envio_real' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $producto_almacen = RecepcionAlmacen::find($request->id_producto_almacen);

            if (!$producto_almacen) {
                DB::rollBack();
                return $this->sendError('Este producto no se encuentra en almacen', 'error', 404);
            }

            // Solo los registros de ESTE producto dentro del canje
            RecepcionAlmacen::where('id_canje', $producto_almacen->id_canje)
                ->where('id_producto', $producto_almacen->id_producto)
                ->update([
                    'estatus' => 'entregado',
                    'costo_envio_real' => $request->costo_envio_real,
                ]);

            $user = Auth::user();
            BitacoraEventos::create([
                'evento' => 'Se confirmó recepción de un producto en almacen',
                'descripcion' => "El usuario con id: {$user->id} confirmó la entrega del producto {$producto_almacen->id_producto} del canje {$producto_almacen->id_canje} con costo de envío real: {$request->costo_envio_real}",
                'id_usuario' => $user->id,
            ]);

            DB::commit();
            return $this->sendResponse('Confirmada recepción del producto correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al confirmar la recepción del producto', $th->getMessage(), 500);
        }
    }
    /**
     * Agrega plataforma, folio y datos del usuario del canje.
     * Requiere que cada registro traiga id_canje e id_plataforma.
     */
    private function enriquecerCanjes($registros)
    {
        $plataformas = Plataformas::pluck('nombre', 'id');

        return $this->folioCanjeResolver
            ->enriquecer($registros, $plataformas)
            ->map(function ($r) {
                // El resolver deja "plataforma" con el nombre ("club bohn");
                // el front usa el slug ("club_bohn") para colores y acciones
                $r->plataforma_nombre = $r->plataforma;
                $r->plataforma = $r->plataforma ? str_replace(' ', '_', $r->plataforma) : null;
                $r->folio_canje = $r->folio;
                return $r;
            });
    }
}
