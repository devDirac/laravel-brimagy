<?php

namespace App\Http\Controllers\API;

use App\Models\UserBrimagy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use App\Models\BitacoraEventos;
use App\Models\CatalogoTipoUsuarios;
use App\Models\UserClub;
use App\Models\UsuariosPlataforma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends BaseController
{
    public $invalidFormatMessage = 'Formato invalido';

    public function crearUsuarioPlataforma(Request $request)
    {
        DB::beginTransaction();

        try {
            $usuarioPlataforma = UsuariosPlataforma::where('id', $request->id_usuario)->first();

            $validator = Validator::make($request->all(), [
                'name' => 'required|string',
                'email' => [
                    'required',
                    'string',
                    'email',
                    Rule::unique('dc_usuarios_plataforma', 'email')->ignore($usuarioPlataforma?->id),
                ],
                'telefono' => 'nullable|string',
                'foto' => 'nullable|string',
                'tipo_usuario' => 'required|integer',
                'password' => $usuarioPlataforma ? 'nullable|string|min:4' : 'required|string|min:4',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            if ($usuarioPlataforma) {
                $datosParaActualizar = $request->only([
                    'usuario',
                    'name',
                    'email',
                    'telefono',
                    'password',
                    'tipo_usuario',
                    'foto'
                ]);

                $datosParaActualizar = array_filter($datosParaActualizar, function ($value) {
                    return !is_null($value) && $value !== '';
                });

                if ($request->filled('password')) {
                    $datosParaActualizar['password'] = bcrypt($request->password);
                }
                $datosParaActualizar['updated_at'] = now()->setTimezone('America/Mexico_City');

                $usuarioActualizado = UsuariosPlataforma::where('id', $request->id_usuario)
                    ->update($datosParaActualizar);

                DB::commit();
                return $this->sendResponse($usuarioActualizado, 'Usuario actualizado exitosamente.');
            }

            $user = UsuariosPlataforma::create([
                'usuario' => $request->usuario,
                'name' => $request->name,
                'email' => $request->email,
                'telefono' => $request->telefono ?? null,
                'password' => bcrypt($request->password),
                'tipo_usuario' => $request->tipo_usuario,
                'foto' => $request->foto ?? null,
                'status' => 'ACTIVE',
            ]);

            DB::commit();

            return $this->sendResponse($user, 'Usuario registrado exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al registrar el usuario', $th->getMessage(), 500);
        }
    }
    public function getCheckEmailHttp(Request $request)
    {
        try {
            // Verifica si el email existe en la base de datos
            $exists = User::where('email', $request->email)->exists();

            return $this->sendResponse([
                'exists' => $exists,
                'available' => !$exists,
                'email' => $request->email
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al verificar email', $th->getMessage(), 500);
        }
    }
    public function getCheckEmailUsuarioPlataforma(Request $request)
    {
        try {
            // Verifica si el email existe en la base de datos
            $exists = UsuariosPlataforma::where('email', $request->email)->exists();

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
    public function getUsuariosPlataforma(Request $request)
    {
        try {
            $usuarios = UsuariosPlataforma::get();

            return $this->sendResponse($usuarios);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los usuarios de plataforma', $th->getMessage(), 500);
        }
    }
    public function getTipoUsuarios(Request $request)
    {
        try {
            $tipo_usuarios = CatalogoTipoUsuarios::get();

            return $this->sendResponse($tipo_usuarios);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener los tipos de usuario', $th->getMessage(), 500);
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
    public function getUsuarios(Request $request)
    {
        try {
            $users = collect();
            if ($request->plataforma === "club_bohn") {
                $users = UserClub::select(
                    'id',
                    'name',
                    'email',
                    'phone',
                    'status',
                    'tipo_usuario',
                )->orderBy('id', 'desc')->get();
            } else {
                $users = User::select(
                    'id',
                    'name',
                    'email',
                    'phone',
                    'status',
                    'tipo_usuario',
                )->orderBy('id', 'desc')->get();
            }
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
                'id_usuario' => 'required|integer',
                'plataforma' => 'required|string',
            ]);
            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('Datos inválidos.', $validator->errors());
            }
            if ($request->plataforma === "club_bohn") {
                $user = UserClub::find($request->id_usuario);
            } else {
                $user = User::find($request->id_usuario);
            }

            if (!$user) {
                DB::rollBack();
                return $this->sendError('Este usuario no existe', [], 404);
            }

            // Preparar datos a actualizar
            $dataToUpdate = $request->only([
                'name',
                'email',
                'phone',
                'foto'
            ]);

            // Filtrar valores vacíos para no sobrescribir con null
            $dataToUpdate = array_filter($dataToUpdate, function ($value) {
                return !is_null($value) && $value !== '';
            });

            $dataToUpdate['updated_at'] = now()->setTimezone('America/Mexico_City');

            if ($request->plataforma === "club_bohn") {
                $usuarioActualizado = UserClub::where('id', $request->id_usuario)
                    ->update($dataToUpdate);
            } else {
                $usuarioActualizado = User::where('id', $request->id_usuario)
                    ->update($dataToUpdate);
                //actualizar de la base de datos de brimagy
                $usuarioBrimagy = UserBrimagy::where('id', $request->id_usuario)
                    ->update($dataToUpdate);
                if (!$usuarioBrimagy) {
                    DB::rollBack();
                    return $this->sendError('Este usuario no existe en la base Brimagy', [], 404);
                }
            }

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
                'id' => 'required',
                'plataforma' => 'required',
            ]);
            if ($validator->fails()) {
                return $this->sendError($this->invalidFormatMessage, $validator->errors());
            }

            if ($request->plataforma === "club_bohn") {
                $user = UserClub::find($request->id);
            } else {
                $user = User::find($request->id);
            }

            if (!$user) {
                return $this->sendError('Este usuario no existe', 'error', 404);
            }
            $user->status = 'ACTIVE';
            $user->save();

            if ($request->plataforma === "club_bohn") {
            } else {
                //se activa en brimagy si no es club bohn
                $userBrimagy = UserBrimagy::find($request->id);

                if (!$userBrimagy) {
                    return $this->sendError('Este usuario no existe en Brimagy', 'error', 404);
                }

                $userBrimagy->status = 'ACTIVE';
                $userBrimagy->save();
            }

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
                'id' => 'required',
                'plataforma' => 'required',
            ]);

            if ($validator->fails()) {
                return $this->sendError($this->invalidFormatMessage, $validator->errors());
            }

            if ($request->plataforma === "club_bohn") {
                $user = UserClub::find($request->id);
            } else {
                $user = User::find($request->id);
            }

            if (!$user) {
                return $this->sendError('Este usuario no existe', 'error', 404);
            }
            $user->status = $request->plataforma === "club_bohn" ? 'INACTIVE' : 'DEACTIVATE';
            $user->save();

            if ($request->plataforma === "club_bohn") {
            } else {
                //se activa en brimagy si no es club bohn
                $userBrimagy = UserBrimagy::find($request->id);

                if (!$userBrimagy) {
                    return $this->sendError('Este usuario no existe en Brimagy', 'error', 404);
                }

                $userBrimagy->status = 'DEACTIVATE';
                $userBrimagy->save();
            }

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
    public function getCatalogoTipoUsuarios(Request $request)
    {
        try {
            $tiposUsuario = collect();
            if ($request->plataforma === "club_bohn") {
                $tiposUsuario = CatalogoTipoUsuarios::where('id_plataforma', 1)->get();
            } else {
                $tiposUsuario = CatalogoTipoUsuarios::where('id_plataforma', 2)->get();
            }
            return $this->sendResponse($tiposUsuario);
        } catch (\Throwable $th) {
            return $this->sendError('Error', $th, 500);
        }
    }
}
