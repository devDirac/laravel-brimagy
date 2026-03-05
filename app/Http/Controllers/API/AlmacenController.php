<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\BitacoraEventos;
use App\Models\RecepcionAlmacen;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AlmacenController extends BaseController
{
    public function getProductosAlmacen(Request $request)
    {
        try {
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
                    'ra.cantidad_almacen',
                    'ra.fecha',
                    'ra.comentarios',
                    'ra.estatus',
                    'ra.guia',
                    DB::raw('GROUP_CONCAT(CASE WHEN f.tipo_archivo = "pdf" THEN f.nombre_factura END) as nombre_factura'),
                    DB::raw('GROUP_CONCAT(CASE WHEN f.tipo_archivo = "pdf" THEN f.url_factura END) as url_factura'),
                    DB::raw('COUNT(CASE WHEN f.tipo_archivo = "pdf" THEN 1 END) as tiene_factura')
                )
                ->leftJoin('dc_catalogo_productos as cdp', 'ra.id_producto', '=', 'cdp.id')
                ->leftJoin('dc_catalogo_proveedores as cp', 'cdp.id_proveedor', '=', 'cp.id')
                ->leftJoin('dc_orden_compra as oc', 'ra.id_orden_compra', '=', 'oc.id')
                ->leftJoin('dc_facturas as f', 'f.id_orden_compra', '=', 'ra.id_orden_compra')
                ->leftJoin('users as u', 'ra.id_usuario', '=', 'u.id')
                ->groupBy(
                    'ra.id',
                    'ra.id_canje',
                    'ra.id_usuario',
                    'u.name',
                    'u.first_last_name',
                    'u.second_last_name',
                    'ra.id_producto',
                    'cdp.nombre_producto',
                    'cdp.marca',
                    'cdp.sku',
                    'cp.nombre',
                    'ra.id_orden_compra',
                    'oc.no_orden',
                    'ra.cantidad_producto',
                    'ra.cantidad_almacen',
                    'ra.fecha',
                    'ra.comentarios',
                    'ra.estatus',
                    'ra.guia'
                );

            // BÚSQUEDA
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('cdp.nombre_producto', 'LIKE', "%{$search}%")
                        ->orWhere('u.name', 'LIKE', "%{$search}%")
                        ->orWhere('cdp.marca', 'LIKE', "%{$search}%")
                        ->orWhere('cdp.sku', 'LIKE', "%{$search}%")
                        ->orWhere('oc.no_orden', 'LIKE', "%{$search}%");
                });
            }

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
                'id_producto_almacen' => 'required|integer|exists:dc_recepcion_almacen,id'
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $producto = DB::table('dc_recepcion_almacen as ra')
                ->select(
                    'ra.id as id_producto_almacen',
                    'ra.id_canje',
                    'ra.id_usuario',
                    'u.name as nombre_usuario',
                    'u.first_last_name as primer_apellido',
                    'u.second_last_name as segundo_apellido',
                    'ra.id_producto',
                    'cp.nombre as nombre_proveedor',
                    'cdp.nombre_producto',
                    'cdp.marca',
                    'cdp.sku',
                    'ra.id_orden_compra',
                    'oc.no_orden',
                    'ra.cantidad_producto',
                    'ra.cantidad_almacen',
                    'ra.fecha',
                    'ra.comentarios',
                    'ra.estatus',
                    'ra.guia',
                    'ea.evidencias',
                    DB::raw('GROUP_CONCAT(CASE WHEN f.tipo_archivo = "pdf" THEN f.fecha_pago END) as fecha_pago'),
                )
                ->leftJoin('dc_catalogo_productos as cdp', 'ra.id_producto', '=', 'cdp.id')
                ->leftJoin('dc_catalogo_proveedores as cp', 'cdp.id_proveedor', '=', 'cp.id')
                ->leftJoin('dc_orden_compra as oc', 'ra.id_orden_compra', '=', 'oc.id')
                ->leftJoin('users as u', 'ra.id_usuario', '=', 'u.id')
                ->leftJoin('dc_evidencias_almacen as ea', 'ea.id_almacen_producto', '=', 'ra.id')
                ->leftJoin('dc_facturas as f', 'f.id_orden_compra', '=', 'ra.id_orden_compra')
                ->where('ra.id', $request->id_producto_almacen)
                ->groupBy(
                    'ra.id',
                    'ra.id_canje',
                    'ra.id_usuario',
                    'u.name',
                    'u.first_last_name',
                    'u.second_last_name',
                    'ra.id_producto',
                    'cp.nombre',
                    'cdp.nombre_producto',
                    'cdp.marca',
                    'cdp.sku',
                    'ra.id_orden_compra',
                    'oc.no_orden',
                    'ra.cantidad_producto',
                    'ra.cantidad_almacen',
                    'ra.fecha',
                    'ra.comentarios',
                    'ra.estatus',
                    'ra.guia',
                    'ea.evidencias'
                )
                ->first();

            return $this->sendResponse($producto);
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

            // Calcular la nueva cantidad en almacén
            $cantidadAlmacenActual = $producto_almacen->cantidad_almacen ?? 0;
            $nuevaCantidadAlmacen = $cantidadAlmacenActual + $request->cantidad_producto;
            $cantidadTotal = $producto_almacen->cantidad_producto;

            // Validar que no exceda la cantidad total
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

            // Determinar el estatus según las cantidades
            $estatus = $nuevaCantidadAlmacen >= $cantidadTotal
                ? 'en_almacen'
                : 'en_almacen_parcialmente';

            $producto_almacen->update([
                'cantidad_almacen' => $nuevaCantidadAlmacen,
                'estatus' => $estatus,
            ]);

            $user = Auth::user();
            $log = [
                'evento' => 'Recepción de producto en almacén',
                'descripcion' => "El usuario con id: {$user->id} recibió {$request->cantidad_producto} unidades del producto {$producto_almacen->id_producto}.",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse([
                'producto' => $producto_almacen->fresh(),
                'estatus' => $estatus,
                'cantidad_recibida' => $nuevaCantidadAlmacen,
                'cantidad_total' => $cantidadTotal,
                'cantidad_pendiente' => $cantidadTotal - $nuevaCantidadAlmacen,
            ], 'Producto recibido en almacén correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al enviar el producto a otro proveedor', $th->getMessage(), 500);
        }
    }
    public function addGuiaProductoAlmacen(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_producto_almacen' => 'required|integer',
                'guia_producto' => 'required|string',
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

            $producto_almacen->update([
                'guia' => $request->guia_producto,
                'estatus' => "guia_asignada",
            ]);

            $user = Auth::user();
            $log = [
                'evento' => 'Se añadió una guía a un producto en almacen',
                'descripcion' => "El usuario con id: {$user->id} añadió la guía {$request->guia_producto} al producto {$producto_almacen->id_producto}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

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

            $producto_almacen->update([
                'estatus' => "enviado",
            ]);

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

            $producto_almacen->update([
                'estatus' => "entregado",
            ]);

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
