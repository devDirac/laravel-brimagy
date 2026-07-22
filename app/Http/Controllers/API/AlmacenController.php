<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\BitacoraEventos;
use App\Models\CatalogoProductos;
use App\Models\CatalogoProveedores;
use App\Models\Plataformas;
use App\Models\ProductoBrimagy;
use App\Models\ProductoClub;
use App\Models\RecepcionAlmacen;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AlmacenController extends BaseController
{
    public function getProductosAlmacen(Request $request)
    {
        try {

            $plataforma = $request->plataforma === 'club_bohn' ? 'club bohn' : $request->plataforma;
            // es club bohn
            $plataformaModel = Plataformas::where('nombre', $plataforma)->first();
            if (!$plataformaModel) {
                return $this->sendError('La plataforma ' . $request->plataforma . ' no existe', 'error', 404);
            }
            $id_plataforma = $plataformaModel->id;

            $query = DB::table('dc_recepcion_almacen as ra')
                ->select(
                    'ra.id',
                    'ra.id_canje',
                    'ra.id_usuario',
                    'u.name as nombre_usuario',
                    'u.first_last_name as primer_apellido',
                    'u.second_last_name as segundo_apellido',
                    'ra.id_producto',
                    'cdp.nombre_producto',
                    'cdp.marca',
                    'cdp.sku',
                    'cp.nombre as nombre_proveedor',
                    'ra.id_orden_compra',
                    'oc.no_orden',
                    'ra.cantidad_producto',
                    DB::raw('(
            SELECT SUM(ra2.cantidad_almacen)
            FROM dc_recepcion_almacen ra2
            WHERE ra2.id_canje = ra.id_canje
        ) as cantidad_almacen'),
                    'ra.fecha',
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
                ->leftJoin('dc_catalogo_proveedores as cp', 'cdp.id_proveedor', '=', 'cp.id')
                ->leftJoin('dc_orden_compra as oc', 'ra.id_orden_compra', '=', 'oc.id')
                ->leftJoin('users as u', 'ra.id_usuario', '=', 'u.id')
                ->whereIn('ra.id', function ($sub) {
                    $sub->selectRaw('MIN(id)')
                        ->from('dc_recepcion_almacen')
                        ->whereNotNull('id_canje')
                        ->groupBy('id_canje');
                })
                ->whereNot('ra.estatus', 'entregado')
                ->where('cdp.id_plataforma', $id_plataforma);

            $productosAlmacen = $query->orderBy('ra.created_at', 'desc')->get();

            return $this->sendResponse($productosAlmacen);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los productos en almacen', $th, 500);
        }
    }
    public function getProductoAlmacenPorId(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_orden_compra' => 'required|string'
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $productos = DB::table('dc_recepcion_almacen as ra')
                ->select(
                    'ra.id as id_producto_almacen',
                    'ra.id_canje',
                    'ra.id_usuario',
                    'u.name as nombre_usuario',
                    'u.first_last_name as primer_apellido',
                    'u.second_last_name as segundo_apellido',
                    'ra.id_producto',
                    'cp.nombre as nombre_proveedor',
                    'ra.id_proveedor',
                    'cdp.nombre_producto',
                    'cdp.marca',
                    'cdp.sku',
                    'cdp.costo_sin_iva',
                    'cdp2.nombre_producto as nombre_producto_nuevo',
                    'cdp2.marca as marca_nuevo',
                    'cdp2.sku as sku_nuevo',
                    'ra.precio_compra',
                    'ra.id_orden_compra',
                    'oc.no_orden',
                    'ra.cantidad_producto',
                    'ra.cantidad_almacen',
                    'ra.fecha',
                    'ra.comentarios',
                    'ra.estatus',
                    'ra.guia',
                    'ra.imei',
                    'ra.no_serie',
                    'ea.evidencias',
                    DB::raw('(
            SELECT GROUP_CONCAT(CASE WHEN f.tipo_archivo = "pdf" THEN f.fecha_pago END)
            FROM dc_facturas f
            WHERE f.id_orden_compra = ra.id_orden_compra
        ) as fecha_pago'),
                )
                ->leftJoin('dc_catalogo_productos as cdp', 'ra.id_producto', '=', 'cdp.id')
                ->leftJoin('dc_catalogo_productos as cdp2', 'ra.id_producto_nuevo', '=', 'cdp2.id')
                ->leftJoin('dc_catalogo_proveedores as cp', 'ra.id_proveedor', '=', 'cp.id')
                ->leftJoin('dc_orden_compra as oc', 'ra.id_orden_compra', '=', 'oc.id')
                ->leftJoin('users as u', 'ra.id_usuario', '=', 'u.id')
                ->leftJoin('dc_evidencias_almacen as ea', 'ea.id_almacen_producto', '=', 'ra.id')
                ->where('ra.id_orden_compra', $request->id_orden_compra)
                ->get();

            //$base = $productos->first();
            $base = $productos->sortBy('id_producto_almacen')->first();

            $resultado = [
                'id_producto_almacen' => $base->id_producto_almacen,
                'id_canje' => $base->id_canje,
                'id_usuario' => $base->id_usuario,
                'nombre_usuario' => $base->nombre_usuario,
                'primer_apellido' => $base->primer_apellido,
                'segundo_apellido' => $base->segundo_apellido,
                'id_producto' => $base->id_producto,
                'nombre_producto' => $base->nombre_producto,
                'nombre_proveedor' => $base->nombre_proveedor,
                'marca' => $base->marca,
                'sku' => $base->sku,
                'costo_sin_iva' => $base->costo_sin_iva,
                'id_orden_compra' => $base->id_orden_compra,
                'no_orden' => $base->no_orden,
                'cantidad_producto' => $base->cantidad_producto,
                'estatus' => $base->estatus,
                'fecha' => $base->fecha,
                'fecha_pago' => $base->fecha_pago,
                'evidencias' => $base->evidencias,
                'guia' => $base->guia,
                'imei' => $base->imei,
                'no_serie' => $base->no_serie,
                'comentarios' => $base->comentarios,
                // Total sumado de todos los registros
                'cantidad_almacen' => $productos->sum('cantidad_almacen'),

                // Detalle de cada registro por separado
                'productos' => $productos
                    ->filter(fn($p) => $p->precio_compra > 0)
                    ->map(fn($p) => [
                        'id_producto_almacen' => $p->id_producto_almacen,
                        'id_proveedor' => $p->id_proveedor,
                        'nombre_proveedor' => $p->nombre_proveedor,
                        'nombre_producto_nuevo' => $p->nombre_producto_nuevo,
                        'marca_nuevo' => $p->marca_nuevo,
                        'sku_nuevo' => $p->sku_nuevo,
                        'precio_compra' => $p->precio_compra,
                        'cantidad_almacen' => $p->cantidad_almacen,
                        'imei' => $p->imei,
                        'no_serie' => $p->no_serie,
                        'comentarios' => $p->comentarios,
                        //'evidencias'           => $p->evidencias,
                    ])->values()
            ];

            return $this->sendResponse($resultado);
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al obtener el producto en almacen', $th, 500);
        }
    }
    public function recibirProductoAlmacen(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_producto_almacen' => 'required|integer',
                'cantidad_producto' => 'required|integer',
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

            $cantidadAlmacenActual = RecepcionAlmacen::where('id_orden_compra', $producto_almacen->id_orden_compra)
                ->sum('cantidad_almacen');

            $nuevaCantidadAlmacen = $cantidadAlmacenActual + $request->cantidad_producto;
            $cantidadTotal = $producto_almacen->cantidad_producto;

            if ($nuevaCantidadAlmacen > $cantidadTotal) {
                DB::rollBack();
                return $this->sendError(
                    'La cantidad a recibir excede la cantidad total del pedido',
                    [
                        'cantidad_pendiente' => $cantidadTotal - $cantidadAlmacenActual,
                        'cantidad_recibida' => $cantidadAlmacenActual,
                        'cantidad_total' => $cantidadTotal
                    ],
                    400
                );
            }

            $user = Auth::user();
            // Determinar el estatus según las cantidades
            $estatus = $nuevaCantidadAlmacen >= $cantidadTotal
                ? 'en_almacen'
                : 'en_almacen_parcialmente';

            if ($request->tipo_registro == "normal") {
                $producto_almacen->update([
                    'id_proveedor' => $request->id_proveedor,
                    'cantidad_almacen' => $request->cantidad_producto,
                    'imei' => $request->imei,
                    'no_serie' => $request->no_serie,
                    'comentarios' => $request->comentarios,
                    'estatus' => $estatus,
                ]);

                $producto_catalogo = CatalogoProductos::find($request->id_producto);

                $producto_catalogo->update([
                    'id_proveedor' => $request->id_proveedor,
                ]);

                $log = [
                    'evento' => 'Recepción de producto en almacén',
                    'descripcion' => "El usuario con id: {$user->id} recibió {$request->cantidad_producto} unidades del producto {$producto_almacen->id_producto}.",
                    'id_usuario' => $user->id,
                ];
            } else {
                $datosProducto = CatalogoProductos::where('id', $request->id_producto)->first();

                //datos del producto existente
                $nombre_producto = $datosProducto->nombre_producto;
                $descripcion = $datosProducto->descripcion;
                $marca = $datosProducto->marca;
                $sku = $datosProducto->sku;
                $color = $datosProducto->color;
                $talla = $datosProducto->talla;
                $id_proveedor = $request->id_proveedor;
                $id_catalogo = $datosProducto->id_catalogo;
                $costo_con_iva = round($request->precio_compra * 1.16);
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
                $id_plataforma = $datosProducto->id_plataforma;
                $tipo_producto = $datosProducto->tipo_producto;

                //primero insertamos el nuevo producto en la tabla awards de brimagy para obtener el nuevo id y relacionarla con nuestra tabla dc_catalogo_productos
                if ($request->plataforma === 'club_bohn') {
                    $productoTablaBrimagy = DB::connection('mysql_club_bohn')
                        ->table('awards as a')
                        ->where('a.id', $datosProducto->id_producto_brimagy)->first();

                    $productoNuevoDesdeBrimagy = ProductoClub::create([
                        'desc' => $productoTablaBrimagy->desc,
                        'required_score' => $productoTablaBrimagy->required_score,
                        'sub_category_id' => $productoTablaBrimagy->sub_category_id,
                        'photo_name' => $productoTablaBrimagy->photo_name,
                        'sku' => $productoTablaBrimagy->sku,
                        'features' => $productoTablaBrimagy->features,
                        'TyC' => $productoTablaBrimagy->TyC,
                        'validity' => $productoTablaBrimagy->validity,
                        'status' => $productoTablaBrimagy->status,
                        'stock' => $productoTablaBrimagy->stock,
                        'score_promotions' => $productoTablaBrimagy->score_promotions,
                        'NEW' => $productoTablaBrimagy->NEW,
                    ]);
                } else {
                    $productoTablaBrimagy = DB::connection('mysql_brimagy')
                        ->table('awards as a')
                        ->where('a.id', $datosProducto->id_producto_brimagy)->first();

                    $productoNuevoDesdeBrimagy = ProductoBrimagy::create([
                        'desc' => $productoTablaBrimagy->desc,
                        'required_score' => $productoTablaBrimagy->required_score,
                        'sub_category_id' => $productoTablaBrimagy->sub_category_id,
                        'photo_name' => $productoTablaBrimagy->photo_name,
                        'sku' => $productoTablaBrimagy->sku,
                        'features' => $productoTablaBrimagy->features,
                        'TyC' => $productoTablaBrimagy->TyC,
                        'validity' => $productoTablaBrimagy->validity,
                        'status' => $productoTablaBrimagy->status,
                        'stock' => $productoTablaBrimagy->stock,
                        'score_ambassadors' => $productoTablaBrimagy->score_ambassadors,
                        'new' => $productoTablaBrimagy->new,
                    ]);
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
                    'id_producto_brimagy' => $productoNuevoDesdeBrimagy->id,
                    'id_plataforma' => $id_plataforma,
                    'tipo_producto' => $tipo_producto,
                ]);

                $producto_almacen_nuevo = RecepcionAlmacen::create([
                    //datos que deben duplicarse
                    'id_canje' => $producto_almacen->id_canje,
                    'id_usuario' => $producto_almacen->id_usuario,
                    'id_producto' => $producto_almacen->id_producto,
                    'cantidad_producto' => $producto_almacen->cantidad_producto,
                    'id_orden_compra' => $producto_almacen->id_orden_compra,
                    'fecha' => $producto_almacen->fecha,
                    'estatus' => $estatus,
                    //nuevos datos a insertar
                    'id_producto_nuevo' => $producto->id,
                    'precio_compra' => $request->precio_compra,
                    'id_proveedor' => $request->id_proveedor,
                    'cantidad_almacen' => $request->cantidad_producto,
                    'imei' => $request->imei,
                    'no_serie' => $request->no_serie,
                    'comentarios' => $request->comentarios,
                ]);

                $log = [
                    'evento' => 'Recepción de producto en almacén con nuevo precio',
                    'descripcion' => "El usuario con id: {$user->id} recibió {$request->cantidad_producto} unidades del producto {$producto->id} con un nuevo precio de {$request->precio_compra}.",
                    'id_usuario' => $user->id,
                ];
            }

            // Actualizar todos los registros con el mismo id_orden_compra
            RecepcionAlmacen::where('id_orden_compra', $producto_almacen->id_orden_compra)
                ->update(['estatus' => $estatus]);

            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse([
                'producto' => $producto_almacen->fresh(),
                'estatus' => $estatus,
                'cantidad_recibida' => $request->cantidad_producto,
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

            //datos del producto existente
            $nombre_producto = $datosProducto->nombre_producto;
            $descripcion = $datosProducto->descripcion;
            $marca = $datosProducto->marca;
            $sku = $datosProducto->sku;
            $color = $datosProducto->color;
            $talla = $datosProducto->talla;
            $id_proveedor = $request->id_proveedor;
            $id_catalogo = $datosProducto->id_catalogo;
            $costo_con_iva = round($request->precio_compra * 1.16);
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

            if ($request->plataforma === "club_bohn") {
                $producto_brimagy = ProductoClub::create([
                    'desc' => $nombre_producto,
                    'features' => $descripcion,
                    'required_score' => $puntos,
                    'sub_category_id' => $id_catalogo,
                    'photo_name' => $foto_producto,
                    'sku' => $sku,
                    'TyC' => "",
                    'validity' => "",
                ]);
            } else {
                $producto_brimagy = ProductoBrimagy::create([
                    'desc' => $nombre_producto,
                    'features' => $descripcion,
                    'required_score' => $puntos,
                    'sub_category_id' => $id_catalogo,
                    'photo_name' => $foto_producto,
                    'sku' => $sku,
                    'TyC' => "",
                    'validity' => "",
                ]);
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
                'id_orden_compra' => 'required|integer',
                'guia_producto' => 'required|string',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $existe = RecepcionAlmacen::where('id_orden_compra', $request->id_orden_compra)->exists();

            if (!$existe) {
                DB::rollBack();
                return $this->sendError('Este producto no se encuentra en almacen', 'error', 404);
            }

            RecepcionAlmacen::where('id_orden_compra', $request->id_orden_compra)
                ->update([
                    'guia' => $request->guia_producto,
                    'estatus' => 'guia_asignada',
                ]);

            $user = Auth::user();
            BitacoraEventos::create([
                'evento' => 'Se añadió una guía a un producto en almacen',
                'descripcion' => "El usuario con id: {$user->id} añadió la guía {$request->guia_producto} a la orden {$request->id_orden_compra}",
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

            // Actualizar todos los registros
            RecepcionAlmacen::where('id_orden_compra', $producto_almacen->id_orden_compra)
                ->update(['estatus' => "enviado"]);

            $user = Auth::user();
            $log = [
                'evento' => 'Se envió un producto de almacén',
                'descripcion' => "El usuario con id: {$user->id} envió el producto {$producto_almacen->id_producto}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

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

            // Actualizar todos los registros
            RecepcionAlmacen::where('id_orden_compra', $producto_almacen->id_orden_compra)
                ->update(['estatus' => "entregado"]);

            $user = Auth::user();
            $log = [
                'evento' => 'Se confirmó recepción de un producto en almacen',
                'descripcion' => "El usuario con id: {$user->id} confirmó recepción del producto {$producto_almacen->id_producto}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse('Confirmada recepción del producto correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al confirmar la recepción del producto', $th->getMessage(), 500);
        }
    }
}
