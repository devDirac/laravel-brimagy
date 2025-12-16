<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Utils\MailSend;
use Illuminate\Support\Facades\DB;
use App\Models\Tokens;
use App\Models\ProcesosTokens;
use App\Models\BitacoraEventos;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Info(
 *     title="API Documentation",
 *     version="1.0.0",
 *     description="Documentación de la API del proyecto",
 *     @OA\Contact(
 *         email="soporte@tuempresa.com"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Servidor de desarrollo"
 * )
 * 
 * @OA\SecurityScheme(
 *     type="http",
 *     securityScheme="bearerAuth",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class AuthController extends BaseController
{

    public $mailValidation = 'required|email';
    public $invalidFormatMessage = 'Formato invalido';

    public function signup(Request $request)
    {
        // Usamos una transacción para garantizar que si algo falla, no se queden registros a medias
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string',
                'usuario' => 'required|string',
                'correo' => 'required|string|email|unique:users,correo',
                'telefono' => 'nullable|string',
                'password' => 'required|string',
                'permisos' => 'required|integer',
                'foto' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $user = User::create([
                'nombre' => $request->nombre,
                'usuario' => $request->usuario,
                'correo' => $request->correo,
                'telefono' => $request->telefono ?? null,
                'password' => bcrypt($request->password),
                'tipo_usuario' => $request->permisos,
                'foto' => $request->foto ?? null,
                'activo' => 1,
            ]);

            DB::commit();

            return $this->sendResponse($user, 'Usuario registrado exitosamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al registrar el usuario', $th->getMessage(), 500);
        }
    }

    public function signin(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required',
                'password' => 'required',
            ]);
            if ($validator->fails()) {
                return $this->sendError($this->invalidFormatMessage, $validator->errors());
            }
            if (Auth::attempt(['email' => $request->email, 'password' => $request->password, 'status' => 'ACTIVE'])) {
                $user = Auth::user();
                $users['data'] = $user;
                $token = $user->createToken('MyAuthBrimagy');
                $users['token'] = $token->plainTextToken;
                unset($user->created_at);
                unset($user->updated_at);

                $log['evento'] = 'Inicio de sesión';
                $log['descripcion'] = "El usuario con id: {$user->id} inicio sesión";
                $log['id_usuario'] = $user->id;
                BitacoraEventos::create($log);

                return $this->sendResponse($users);
            } else {
                return $this->sendError('La contraseña o el usuario son incorrectos o el usuario ya fue dado de baja', ['error' => ''], 401);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'Ocurrió un error',
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
            ], 500);
        }
    }

    public function logOut(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();
            Auth::guard('web')->logout();
            return $this->sendResponse('Cierre de sesión exitoso.');
        } catch (\Throwable $th) {
            return $this->sendError('Error al cerrar la sesión del usuario', $th, 500);
        }
    }

    public function passwordRecoverSendLink(Request $request)
    {
        DB::beginTransaction();

        try {
            // Validación
            $validator = Validator::make($request->all(), [
                'correo' => 'required|email'
            ]);

            if ($validator->fails()) {
                return $this->sendError('El correo es requerido y debe ser válido', $validator->errors(), 422);
            }

            // Buscar usuario
            $user = User::where('correo', $request->correo)->first();

            if (!$user) {
                // Por seguridad, no revelar si el correo existe o no
                return $this->sendResponse('Si el correo existe, recibirás un enlace de recuperación');
            }

            // Buscar proceso
            $proceso = ProcesosTokens::where('proceso', 'recuperar password')->first();

            if (!$proceso) {
                //Log::error('No existe el proceso "recuperar password" en la tabla procesos_tokens');
                return $this->sendError('Error en la configuración del sistema', [], 500);
            }

            // Verificar si ya existe un token activo
            $tokenExistente = Tokens::where('id_token_proceso', $proceso->id)
                ->where('id_usuario', $user->id)
                ->first();

            if ($tokenExistente) {
                DB::rollBack();
                return $this->sendError(
                    'Ya tienes un proceso activo. Verifica tu correo (revisa spam). Podrás solicitar uno nuevo en 24 horas.',
                    [],
                    429
                );
            }

            // Generar token seguro
            $token = bin2hex(random_bytes(32));

            // Crear registro del token
            $tokenCreated = Tokens::create([
                'token' => $token,
                'id_usuario' => $user->id,
                'id_token_proceso' => $proceso->id
            ]);

            // Preparar URL
            $frontUrl = env('APP_FRONT_URL', '');
            $resetUrl = $frontUrl . '/recupera-password-validacion?token=' . $token;

            // Enviar correo
            try {
                $sendMail = new MailSend();
                $mailResponse = $sendMail->sendMailPro([
                    'correo' => $user->correo,
                    'nombre' => $user->nombre,
                    'titulo' => '<h1>' . $user->nombre . '</h1><h3>Esqueleto te notifica</h3>',
                    'html' => '<h3>Has iniciado el proceso para la recuperación de tu contraseña</h3> <p>Para restablecer tu contraseña, haz clic en el siguiente enlace:</p><p><a href="' . $resetUrl . '" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Restablecer Contraseña</a></p>
                    <p>O copia y pega este enlace en tu navegador:</p>
                    <p>' . $resetUrl . '</p><br><p><strong>Este enlace expirará en 24 horas</strong></p><p>Si no solicitaste este cambio, ignora este correo.</p>',
                ], 'emails.general', 'Recuperación de contraseña');
            } catch (\Exception $mailError) {
                DB::rollBack();
                Log::error('Error al enviar correo de recuperación: ' . $mailError->getMessage());
                return $this->sendError(
                    'Error al enviar el correo. Verifica la configuración de correo.',
                    ['error' => $mailError->getMessage()],
                    500
                );
            }

            // Registrar en bitácora
            BitacoraEventos::create([
                'evento' => 'Solicitud de recuperación de contraseña',
                'descripcion' => 'El usuario con id: ' . $user->id . ' (' . $user->correo . ') solicitó recuperar su contraseña',
                'id_usuario' => $user->id
            ]);

            DB::commit();

            return $this->sendResponse(
                ['email' => $user->correo],
                'Se ha enviado un correo con las instrucciones para recuperar tu contraseña'
            );
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al recuperar la contraseña', $th, 500);
        }
    }

    public function passwordRecoverTokenValidation(Request $request)
    {
        try {
            $input = $request->all();
            $validator = Validator::make($input, [
                'token' => 'required'
            ]);
            if ($validator->fails()) {
                return $this->sendError('El token es requerido', $validator->errors(), 409);
            }
            $tokenValidation = Tokens::where('token', $input['token'])->get()->first();
            if (!$tokenValidation) {
                return $this->sendError('Este token no es válido o ya fue utilizado', "error", 400);
            }
            return $this->sendResponse($tokenValidation);
        } catch (\Throwable $th) {
            return $this->sendError('Error', $th, 500);
        }
    }

    public function passwordReset(Request $request, User $usuario)
    {
        try {
            $input = $request->all();
            $validator = Validator::make($input, [
                'contrasena' => 'required',
                'contrasenaConfirm' => 'required',
                'token' => 'required'

            ]);
            if ($validator->fails()) {
                return $this->sendError('La contraseña, la confirmación de la contraseña y el token son requeridos', $validator->errors(), 409);
            }
            if ($input['contrasena'] !== $input['contrasenaConfirm']) {
                return $this->sendError('La contraseña y la confirmación de la contraseña no son iguales', $validator->errors(), 400);
            }

            $infoTokenUser = DB::table('tokens')
                ->join('users', 'users.id', '=', 'tokens.id_usuario')
                ->select('tokens.id', 'tokens.token', 'tokens.id_usuario', 'users.nombre', 'users.correo')
                ->where('tokens.token', $input['token'])->get()->first();

            if (!$infoTokenUser) {
                return $this->sendError('No existe relación del token con el usuario', [], 404);
            }

            $update['password'] = bcrypt($input['contrasena']);
            $usuario->where('id', '=', $infoTokenUser->id_usuario)->update($update);

            DB::table('tokens')->where('token', $input['token'])->delete();

            $log['evento'] = 'Se actualizo la contraseña';
            $log['descripcion'] = 'El usuario con id:' . $infoTokenUser->id_usuario . ' actualizo su contraseña';
            $log['id_usuario'] = $infoTokenUser->id_usuario;
            BitacoraEventos::create($log);

            return $this->sendResponse('Se ha actualizado la contraseña con éxito.');
        } catch (\Throwable $th) {
            return $this->sendError('Error', $th, 500);
        }
    }
}
