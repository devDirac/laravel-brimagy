<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\BitacoraEventos;
use App\Models\CatalogoCategoria;
use App\Models\SubCategoria;

class CategoriasController extends BaseController
{
    public function crearCategoria(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string',
                'category_id' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $producto = SubCategoria::create([
                'desc' => $request->nombre,
                'category_id' => $request->category_id,
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
    public function getCategoriasPrincipal()
    {
        try {
            $categorias = CatalogoCategoria::get();

            return $this->sendResponse($categorias);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener las categorías', $th, 500);
        }
    }
    public function getCategorias()
    {
        try {
            $categorias = SubCategoria::get();

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
                'id' => 'required|integer',
                'id_category' => 'required|integer',
            ]);
            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('Falta el id de la categoría.', $validator->errors());
            }
            $categoria = SubCategoria::find($request->id);
            if (!$categoria) {
                DB::rollBack();
                return $this->sendError('Esta categoria no existe', [], 404);
            }

            $datosParaActualizar = $request->only([
                'desc',
                'id_category'
            ]);

            $datosParaActualizar = array_filter($datosParaActualizar, function ($value) {
                return !is_null($value) && $value !== '';
            });

            $datosParaActualizar['updated_at'] = now()->setTimezone('America/Mexico_City');

            $categoriaActualizada = SubCategoria::where('id', $request->id)
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
                'id' => 'required|integer'
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('Falta el id de la categoria.', $validator->errors());
            }

            $categoria = SubCategoria::find($request->id);

            if (!$categoria) {
                DB::rollBack();
                return $this->sendError('Esta categoria no existe', [], 404);
            }

            $categoria->update([
                'status' => "INACTIVE",
            ]);

            $user = Auth::user();
            $log['evento'] = 'Eliminación de categoria';
            $log['descripcion'] = "El usuario con id: {$user->id} desactivó una categoría";
            $log['id_usuario'] = $user->id;
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse('Categoría eliminada exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al eliminar la categoría', $th->getMessage(), 500);
        }
    }
    public function reactivarCategoria(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required|integer'
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('Falta el id de la categoria.', $validator->errors());
            }

            $categoria = SubCategoria::find($request->id);

            if (!$categoria) {
                DB::rollBack();
                return $this->sendError('Esta categoria no existe', [], 404);
            }

            $categoria->update([
                'status' => "ACTIVE",
            ]);

            $user = Auth::user();
            $log['evento'] = 'Reactivación de categoria';
            $log['descripcion'] = "El usuario con id: {$user->id} reactivó una categoría";
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
