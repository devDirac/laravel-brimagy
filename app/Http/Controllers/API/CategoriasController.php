<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\BitacoraEventos;
use App\Models\CatalogoCategoria;

class CategoriasController extends BaseController
{
    public function crearCategoria(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string',
                //'descripcion' => 'required|string',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $producto = CatalogoCategoria::create([
                'desc' => $request->nombre,
                //'descripcion' => $request->descripcion,
            ]);

            $user = Auth::user();
            $log['evento'] = 'Creación de categoría';
            $log['descripcion'] = "El usuario con id: {$user->id} añadio la categoría: {$request->nombre}";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse($producto, 'Categoría registrada exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al registrar la categoría', $th->getMessage(), 500);
        }
    }
    public function getCategorias()
    {
        try {
            $categorias = CatalogoCategoria::get();

            return $this->sendResponse($categorias);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener las categorías', $th, 500);
        }
    }
    public function editarCategoria(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|integer|exists:awards_categories,id'
            ]);
            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('Falta el id de la categoría.', $validator->errors());
            }
            $categoria = CatalogoCategoria::find($request->id);
            if (!$categoria) {
                DB::rollBack();
                return $this->sendError('Esta categoria no existe', [], 404);
            }

            // Preparar datos a actualizar
            $datosParaActualizar = $request->only([
                'desc',
                //'descripcion'
            ]);

            $datosParaActualizar = array_filter($datosParaActualizar, function ($value) {
                return !is_null($value) && $value !== '';
            });

            $datosParaActualizar['updated_at'] = now()->setTimezone('America/Mexico_City');

            $categoriaActualizada = CatalogoCategoria::where('id', $request->id)
                ->update($datosParaActualizar);

            DB::commit();

            $userLog = Auth::user();
            $log['evento'] = 'Se editó la información de una categoría';
            $log['descripcion'] = "El usuario con id: {$userLog->id} actualizó la categoría con id: {$request->id}";
            $log['id_usuario'] = $userLog->id;
            BitacoraEventos::create($log);

            return $this->sendResponse("Se ha actualizado la categoría con éxito");
        } catch (\Throwable $th) {
            return $this->sendError('Error al actualizar la categoría', $th, 500);
        }
    }
    public function eliminarCategoria(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|integer|exists:awards_categories,id'
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('Falta el id de la categoria.', $validator->errors());
            }

            $categoria = CatalogoCategoria::find($request->id);

            if (!$categoria) {
                DB::rollBack();
                return $this->sendError('Esta categoria no existe', [], 404);
            }

            // Eliminar la categoria
            $categoria->delete();

            $user = Auth::user();
            $log['evento'] = 'Eliminación de categoria';
            $log['descripcion'] = "El usuario con id: {$user->id} eliminó una categoría";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse('Categoría eliminada exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al eliminar la categoría', $th->getMessage(), 500);
        }
    }
}
