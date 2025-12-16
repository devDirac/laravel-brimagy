<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\BitacoraEventos;
use App\Models\CatalogoProveedores;

class ProveedorController extends BaseController
{
    public function crearProveedor(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string',
                'descripcion' => 'required|string',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $proveedor = CatalogoProveedores::create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
            ]);

            $user = Auth::user();
            $log['evento'] = 'Creación de proveedor';
            $log['descripcion'] = "El usuario con id: {$user->id} añadio el proveedor: {$request->nombre}";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse($proveedor, 'Proveedor registrado exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al registrar el proveedor', $th->getMessage(), 500);
        }
    }
    public function getProveedores()
    {
        try {
            $proveedores = CatalogoProveedores::get();

            return $this->sendResponse($proveedores);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los proveedores', $th, 500);
        }
    }
    public function editarProveedor(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|integer|exists:dc_catalogo_proveedores,id'
            ]);
            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('Falta el id del proveedor.', $validator->errors());
            }
            $proveedor = CatalogoProveedores::find($request->id);
            if (!$proveedor) {
                DB::rollBack();
                return $this->sendError('Este proveedor no existe', [], 404);
            }

            // Preparar datos a actualizar
            $datosParaActualizar = $request->only([
                'nombre',
                'descripcion'
            ]);

            $datosParaActualizar = array_filter($datosParaActualizar, function ($value) {
                return !is_null($value) && $value !== '';
            });

            $datosParaActualizar['updated_at'] = now()->setTimezone('America/Mexico_City');

            $proveedorActualizado = CatalogoProveedores::where('id', $request->id)
                ->update($datosParaActualizar);

            DB::commit();

            $userLog = Auth::user();
            $log['evento'] = 'Se editó la información de un proveedor';
            $log['descripcion'] = "El usuario con id: {$userLog->id} actualizó el proveedor con id: {$request->id}";
            $log['id_usuario'] = $userLog->id;
            BitacoraEventos::create($log);

            return $this->sendResponse("Se ha actualizado el proveedor con éxito");
        } catch (\Throwable $th) {
            return $this->sendError('Error al actualizar el proveedor', $th, 500);
        }
    }
    public function eliminarProveedor(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|integer|exists:dc_catalogo_proveedores,id'
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('Falta el id del proveedor.', $validator->errors());
            }

            $proveedor = CatalogoProveedores::find($request->id);

            if (!$proveedor) {
                DB::rollBack();
                return $this->sendError('Este proveedor no existe', [], 404);
            }

            // Eliminar la categoria
            $proveedor->delete();

            $user = Auth::user();
            $log['evento'] = 'Eliminación de proveedor';
            $log['descripcion'] = "El usuario con id: {$user->id} eliminó un proveedor";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse('Proveedor eliminado exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al eliminar el proveedor', $th->getMessage(), 500);
        }
    }
}
