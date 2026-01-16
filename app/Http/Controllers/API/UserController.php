<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use App\Models\BitacoraEventos;
use Illuminate\Support\Facades\DB;

class UserController extends BaseController
{
    public $invalidFormatMessage = 'Formato invalido';

    public function getCheckEmailHttp(Request $request)
    {
        try {
            // Verifica si el email existe en la base de datos
            $exists = User::where('correo', $request->email)->exists();

            return $this->sendResponse([
                'exists' => $exists,
                'available' => !$exists,
                'email' => $request->email
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al verificar email', $th->getMessage(), 500);
        }
    }
    public function getCheckUsuarioHttp(Request $request)
    {
        try {
            // Verifica si el usuario existe en la base de datos
            $exists = User::where('usuario', $request->usuario)->exists();

            return $this->sendResponse([
                'exists' => $exists,
                'available' => !$exists,
                'usuario' => $request->usuario
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al verificar el usuario', $th->getMessage(), 500);
        }
    }
    public function getUsuariosPorAgente(Request $request)
    {
        try {
            $usuarios = User::where('agente_id', $request->agente_id)->get();

            return $this->sendResponse($usuarios);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los usuarios', $th->getMessage(), 500);
        }
    }
    public function getUsuarios()
    {
        try {
            $users = User::orderBy('id', 'desc')->get();
            return $this->sendResponse($users);
        } catch (\Throwable $th) {
            return $this->sendError('Error', $th, 500);
        }
    }
    public function editarUsuario(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_usuario' => 'required|integer|exists:users,id'
            ]);
            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('Datos inválidos.', $validator->errors());
            }
            $user = User::find($request->id_usuario);
            if (!$user) {
                DB::rollBack();
                return $this->sendError('Este usuario no existe', [], 404);
            }

            // Preparar datos a actualizar
            $dataToUpdate = $request->only([
                'name',
                'email',
                'telefono',
                'foto'
            ]);

            // Filtrar valores vacíos para no sobrescribir con null
            $dataToUpdate = array_filter($dataToUpdate, function ($value) {
                return !is_null($value) && $value !== '';
            });

            $dataToUpdate['updated_at'] = now()->setTimezone('America/Mexico_City');

            $usuarioActualizado = User::where('id', $request->id_usuario)
                ->update($dataToUpdate);

            DB::commit();

            $userLog = Auth::user();
            $log['evento'] = 'Se editó la información de usuario';
            $log['descripcion'] = "El usuario con id: {$user->id} fue actualizado";
            $log['id_usuario'] = $userLog->id;
            BitacoraEventos::create($log);

            return $this->sendResponse("Se ha actualizado el usuario con éxito");
        } catch (\Throwable $th) {
            return $this->sendError('El correo ingresado ya fue dado de alta anteriormente', $th, 500);
        }
    }
    public function activarUsuario(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required'
            ]);
            if ($validator->fails()) {
                return $this->sendError($this->invalidFormatMessage, $validator->errors());
            }
            $user = User::find($request->id);

            if (!$user) {
                return $this->sendError('Este usuario no existe', 'error', 404);
            }
            $user->status = 'ACTIVE';
            $user->save();

            $userLog = Auth::user();
            $log['evento'] = 'Se actualizo el estatus de usuario';
            $log['descripcion'] = 'El usuario con id:' . $user->id . ' se actualizo su estatus a: ACTIVE';
            $log['id_usuario'] = $userLog->id;
            BitacoraEventos::create($log);

            return $this->sendResponse($user);
        } catch (\Throwable $th) {
            return $this->sendError('Error al activar el usuario', $th, 500);
        }
    }
    public function desactivarUsuario(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required'
            ]);

            if ($validator->fails()) {
                return $this->sendError($this->invalidFormatMessage, $validator->errors());
            }
            $user = User::find($request->id);
            if (!$user) {
                return $this->sendError('Este usuario no existe', 'error', 404);
            }
            $user->status = 'DEACTIVATE';
            $user->save();

            $userLog = Auth::user();
            $log['evento'] = 'Se actualizo el estatus de usuario';
            $log['descripcion'] = 'El usuario con id:' . $user->id . ' se actualizo su estatus a: DEACTIVATE';
            $log['id_usuario'] = $userLog->id;
            BitacoraEventos::create($log);

            return $this->sendResponse($user);
        } catch (\Throwable $th) {
            return $this->sendError('Error al desactivar el usuario', $th, 500);
        }
    }
    public function getUsuarioPorId(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_usuario' => 'required|integer'
            ]);
            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $usuario = User::where('agente_id', $request->id_usuario)
                ->first();
            return $this->sendResponse($usuario);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener el usuario', $th, 500);
        }
    }
    public function getAgentesPorPromotor(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_promotor' => 'required|integer'
            ]);
            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $promotores = User::where('agente_id', $request->id_promotor)
                ->where('tipo_usuario', 3)
                ->get();
            return $this->sendResponse($promotores);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los promotores', $th, 500);
        }
    }
}
