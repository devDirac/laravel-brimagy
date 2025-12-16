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
                'sku' => 'required|string',
                'color' => 'required|string',
                'costo_con_iva' => 'required|integer',
                'costo_sin_iva' => 'required|integer',
                'costo_puntos_con_iva' => 'required|integer',
                'costo_puntos_sin_iva' => 'required|integer',
                'fee_brimagy' => 'required|integer',
                'subtotal' => 'required|integer',
                'envio_base' => 'required|integer',
                'costo_caja' => 'required|integer',
                'envio_extra' => 'required|integer',
                'total_envio' => 'required|integer',
                'total' => 'required|integer',
                'puntos' => 'required|integer',
                'factor' => 'required|integer',
                'tipo_registro' => 'required|string',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $id_proveedor = $request->id_proveedor;
            $id_catalogo = $request->id_catalogo;

            if ($request->tipo_registro === 'excel') {
                // Buscar proveedor por nombre
                $proveedor = CatalogoProveedores::where('nombre', 'like', '%' . $request->proveedor . '%')->first();
                if (!$proveedor) {
                    DB::rollBack();
                    return $this->sendError('El proveedor "' . $request->proveedor . '" no existe', 'error', 404);
                }
                $id_proveedor = $proveedor->id;

                // Buscar categoría por nombre
                $catalogo = CatalogoCategoria::where('nombre', 'like', '%' . $request->catalogo . '%')->first();
                if (!$catalogo) {
                    DB::rollBack();
                    return $this->sendError('La categoría "' . $request->catalogo . '" no existe', 'error', 404);
                }
                $id_catalogo = $catalogo->id;
            }

            // Verificar si ya existe un producto con ese SKU
            $productoExistente = CatalogoProductos::where('sku', $request->sku)->first();

            if ($productoExistente) {
                // Si existe, actualizarlo
                $productoExistente->update([
                    'nombre_producto' => $request->nombre_producto,
                    'descripcion' => $request->descripcion,
                    'marca' => $request->marca,
                    'color' => $request->color,
                    'id_proveedor' => $id_proveedor,
                    'id_catalogo' => $id_catalogo,
                    'costo_con_iva' => $request->costo_con_iva,
                    'costo_sin_iva' => $request->costo_sin_iva,
                    'costo_puntos_con_iva' => $request->costo_puntos_con_iva,
                    'costo_puntos_sin_iva' => $request->costo_puntos_sin_iva,
                    'fee_brimagy' => $request->fee_brimagy,
                    'subtotal' => $request->subtotal,
                    'envio_base' => $request->envio_base,
                    'costo_caja' => $request->costo_caja,
                    'envio_extra' => $request->envio_extra,
                    'total_envio' => $request->total_envio,
                    'total' => $request->total,
                    'puntos' => $request->puntos,
                    'factor' => $request->factor,
                    'updated_at' => now()->setTimezone('America/Mexico_City'),
                ]);

                $user = Auth::user();
                $log['evento'] = 'Actualización de producto';
                $log['descripcion'] = "El usuario con id: {$user->id} actualizó el producto con id: {$productoExistente->id} (SKU: {$request->sku})";
                $log['id_usuario'] = $user->id;
                BitacoraEventos::create($log);

                DB::commit();

                return $this->sendResponse($productoExistente, 'Producto actualizado exitosamente.');
            }

            $producto = CatalogoProductos::create([
                'nombre_producto' => $request->nombre_producto,
                'descripcion' => $request->descripcion,
                'marca' => $request->marca,
                'sku' => $request->sku,
                'color' => $request->color,
                'id_proveedor' => $id_proveedor,
                'id_catalogo' => $id_catalogo,
                'costo_con_iva' => $request->costo_con_iva,
                'costo_sin_iva' => $request->costo_sin_iva,
                'costo_puntos_con_iva' => $request->costo_puntos_con_iva,
                'costo_puntos_sin_iva' => $request->costo_puntos_sin_iva,
                'fee_brimagy' => $request->fee_brimagy,
                'subtotal' => $request->subtotal,
                'envio_base' => $request->envio_base,
                'costo_caja' => $request->costo_caja,
                'envio_extra' => $request->envio_extra,
                'total_envio' => $request->total_envio,
                'total' => $request->total,
                'puntos' => $request->puntos,
                'factor' => $request->factor,
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

            $skusExistentes = CatalogoProductos::whereIn('sku', $request->skus)
                ->pluck('sku')
                ->toArray();

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

    public function getCatalogoProductos()
    {
        try {
            $productos = DB::table('dc_catalogo_productos as cpt')
                ->select(
                    'cpt.id',
                    'cpt.nombre_producto',
                    'cpt.descripcion',
                    'cpt.marca',
                    'cpt.sku',
                    'cpt.color',
                    'cpv.nombre as proveedor',
                    'cc.nombre as catalogo',
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
                ->join('dc_catalogo_categoria as cc', 'cpt.id_catalogo', '=', 'cc.id')
                ->join('dc_catalogo_proveedores as cpv', 'cpt.id_proveedor', '=', 'cpv.id')
                ->orderBy('cpt.id', 'desc')
                ->get();

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

            // Preparar datos a actualizar
            $datosParaActualizar = $request->only([
                'nombre_producto',
                'descripcion',
                'marca',
                'sku',
                'color',
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
                'factor'
            ]);

            $datosParaActualizar = array_filter($datosParaActualizar, function ($value) {
                return !is_null($value) && $value !== '';
            });

            $datosParaActualizar['updated_at'] = now()->setTimezone('America/Mexico_City');

            $productoActualizado = CatalogoProductos::where('id', $request->id_producto)
                ->update($datosParaActualizar);

            DB::commit();

            $userLog = Auth::user();
            $log['evento'] = 'Se editó la información de un producto';
            $log['descripcion'] = "El usuario con id: {$userLog->id} actualizó el producto con id: {$request->id_producto}";
            $log['id_usuario'] = $userLog->id;
            BitacoraEventos::create($log);

            return $this->sendResponse("Se ha actualizado el producto con éxito");
        } catch (\Throwable $th) {
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
}
