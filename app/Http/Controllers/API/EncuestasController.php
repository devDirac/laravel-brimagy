<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Mail\EnviarEncuesta;
use App\Models\BitacoraEventos;
use App\Models\Encuestas;
use App\Models\Notificaciones;
use App\Models\RecepcionAlmacen;
use App\Models\RespuestasEncuesta;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\WhatsAppService;

class EncuestasController extends BaseController
{
    protected $whatsappService;

    public function __construct()
    {
        $this->whatsappService = new WhatsAppService();
    }
    public function createPreguntaEncuesta(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'pregunta' => 'required|string',
                'tipo_encuesta' => 'required|string',
                'tipo_pregunta' => 'required|string',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $encuesta = Encuestas::create([
                'pregunta' => $request->pregunta,
                'tipo_encuesta' => $request->tipo_encuesta,
                'tipo_pregunta' => $request->tipo_pregunta,
                'estatus' => "activa",
            ]);

            DB::commit();

            return $this->sendResponse($encuesta, "Pregunta añadida correctamente.");
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al añadir la pregunta.', $th->getMessage(), 500);
        }
    }
    public function enviarEncuestaUsuario(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_canje' => 'required|integer',
                'tipo_encuesta' => 'required|string',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $encuesta = str_replace('_', ' ', $request->tipo_encuesta);

            $canje = DB::table('swaps_view')
                ->where('id', $request->id_canje)
                ->first();

            if ($canje) {
                $this->enviarWhatsApp($canje, $request->tipo_encuesta);
                $this->enviarCorreo($canje, $request->tipo_encuesta);
            }

            $user = Auth::user();
            $log = [
                'evento' => 'Se envió una encuesta',
                'descripcion' => "El usuario con id: {$user->id} envió una {$encuesta} del canje {$request->id_canje}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

            DB::commit();

            return $this->sendResponse("Encuesta enviada correctamente.");
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al enviar la encuesta.', $th->getMessage(), 500);
        }
    }
    public function getEncuestasDisponibles()
    {
        try {
            $encuestas = DB::table('dc_encuestas as e')
                ->select(
                    'e.tipo_encuesta',
                    DB::raw('COUNT(e.id) as total_preguntas'),
                    DB::raw('COUNT(re.id) as total_respuestas')
                )
                ->leftJoin('dc_respuestas_encuesta as re', 'e.id', '=', 're.id_pregunta')
                ->where('e.estatus', 'activa')
                ->groupBy('e.tipo_encuesta')
                ->get();

            return $this->sendResponse($encuestas, 'Encuestas cargadas correctamente.');
        } catch (\Throwable $th) {
            return $this->sendError('Error al cargar las encuestas', $th, 500);
        }
    }

    public function getPreguntasPorTipo(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tipo_encuesta' => 'required|string'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Falta el tipo de encuesta.', $validator->errors());
            }
            $encuesta = DB::table('dc_encuestas as e')
                ->select(
                    'e.id',
                    'e.pregunta',
                    'e.tipo_encuesta',
                    'e.tipo_pregunta',
                    'e.estatus',
                )
                ->where('e.tipo_encuesta', 'LIKE', "%{$request->tipo_encuesta}%")
                ->orderBy('e.id', 'desc')
                ->get();

            return $this->sendResponse($encuesta, 'Preguntas de la encuesta obtenidas correctamente.');
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener las preguntas de la encuesta', $th, 500);
        }
    }
    public function editarPreguntaEncuesta(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_pregunta' => 'required|integer',
                'pregunta' => 'required|string',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $pregunta = Encuestas::find($request->id_pregunta);

            if (!$pregunta) {
                DB::rollBack();
                return $this->sendError('Esta pregunta no se encuentra', 'error', 404);
            }

            $pregunta->update([
                'pregunta' => $request->pregunta,
            ]);

            $user = Auth::user();
            $log = [
                'evento' => 'Se editó una pregunta de un catálogo',
                'descripcion' => "El usuario con id: {$user->id} editó la pregunta {$request->id_pregunta}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse($pregunta, 'Pregunta editada correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al editar la pregunta', $th->getMessage(), 500);
        }
    }
    public function desactivarPreguntaEncuesta(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_pregunta' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $pregunta = Encuestas::find($request->id_pregunta);

            if (!$pregunta) {
                DB::rollBack();
                return $this->sendError('Esta pregunta no se encuentra', 'error', 404);
            }

            $pregunta->update([
                'estatus' => "desactivada",
            ]);

            $user = Auth::user();
            $log = [
                'evento' => 'Se desactivó una pregunta de un catálogo',
                'descripcion' => "El usuario con id: {$user->id} desactivó la pregunta {$request->id_pregunta}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse($pregunta, 'Pregunta desactivada correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al desactivar la pregunta', $th->getMessage(), 500);
        }
    }
    public function activarPreguntaEncuesta(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'id_pregunta' => 'required|integer',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $pregunta = Encuestas::find($request->id_pregunta);

            if (!$pregunta) {
                DB::rollBack();
                return $this->sendError('Esta pregunta no se encuentra', 'error', 404);
            }

            $pregunta->update([
                'estatus' => "activa",
            ]);

            $user = Auth::user();
            $log = [
                'evento' => 'Se activó una pregunta de un catálogo',
                'descripcion' => "El usuario con id: {$user->id} activó la pregunta {$request->id_pregunta}",
                'id_usuario' => $user->id,
            ];
            BitacoraEventos::create($log);

            DB::commit();
            return $this->sendResponse($pregunta, 'Pregunta activada correctamente.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al activar la pregunta', $th->getMessage(), 500);
        }
    }
    public function getEncuestaPorTipo(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tipo_encuesta' => 'required|string'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Falta el tipo de encuesta.', $validator->errors());
            }

            $encuesta = DB::table('dc_encuestas as e')
                ->select(
                    'e.id',
                    'e.pregunta',
                    'e.tipo_encuesta',
                    'e.tipo_pregunta',
                    'e.estatus',
                    'e.created_at as creacion_pregunta',
                )
                ->where('e.tipo_encuesta', 'LIKE', "%{$request->tipo_encuesta}%")
                ->where('e.estatus', '=', "activa")
                ->orderBy('e.id', 'asc')
                ->get();

            return $this->sendResponse($encuesta, 'Preguntas de la encuesta cargadas correctamente');
        } catch (\Throwable $th) {
            return $this->sendError('Error al cargar las respuestas de la encuesta', $th, 500);
        }
    }
    public function getRespuestasEncuestaPorCanje(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_canje' => 'required|string',
                'tipo_encuesta' => 'required|string'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Falta el id de canje o el tipo de encuesta.', $validator->errors());
            }
            $idOrdenCompraDesencriptado = $this->desencriptarCorto($request->id_canje);
            $encuesta = DB::table('dc_encuestas as e')
                ->select(
                    'e.id',
                    'e.pregunta',
                    'e.tipo_encuesta',
                    'e.tipo_pregunta',
                    'e.estatus',
                    're.respuesta',
                    're.created_at as creacion_respuesta',
                )
                ->leftJoin('dc_respuestas_encuesta as re', 'e.id', '=', 're.id_pregunta')
                ->where('e.tipo_encuesta', 'LIKE', "%{$request->tipo_encuesta}%")
                ->where('re.id_canje', '=', $idOrdenCompraDesencriptado)
                ->orderBy('e.id', 'desc')
                ->get();

            return $this->sendResponse($encuesta, 'Preguntas de la encuesta cargadas correctamente');
        } catch (\Throwable $th) {
            return $this->sendError('Error al cargar las respuestas de la encuesta', $th, 500);
        }
    }
    public function addRespuestasEncuestaUsuario(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_canje' => 'required|string',
                'tipo_encuesta' => 'required|string',
                'respuestas' => 'required|array',
            ]);

            if ($validator->fails()) {
                DB::rollBack();
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }

            $idOrdenCompraDesencriptado = $this->desencriptarCorto($request->id_canje);

            $canje = DB::table('swaps_view')
                ->where('id', $idOrdenCompraDesencriptado)
                ->first();

            if (!$canje) {
                DB::rollBack();
                return $this->sendError('El canje no existe.', [], 404);
            }

            $almacen = DB::table('dc_recepcion_almacen')
                ->where('id_canje', $idOrdenCompraDesencriptado)
                ->first();

            if (!$almacen) {
                DB::rollBack();
                return $this->sendError('Este canje no se encuentra en almacen', 'error', 404);
            }

            $respuestasGuardadas = [];

            foreach ($request->respuestas as $id_pregunta => $respuesta) {
                $preguntaExiste = Encuestas::find($id_pregunta);

                if (!$preguntaExiste) {
                    continue;
                }

                $respuestaGuardada = RespuestasEncuesta::create([
                    'id_pregunta' => $id_pregunta,
                    'id_canje' => $idOrdenCompraDesencriptado,
                    'respuesta' => $respuesta,
                ]);

                $respuestasGuardadas[] = [
                    'id_pregunta' => $id_pregunta,
                    'respuesta' => $respuesta,
                ];
            }
            $tipo_encuesta = str_replace('_', ' ', $request->tipo_encuesta);


            $log['vista'] = 0;
            $log['detalle'] = "El usuario {$canje->name} ha contestado la encuesta de {$tipo_encuesta}";
            $log['id_tipo_notificacion'] = 3;
            $log['id_usuario_creador'] = $canje->user_id;
            $log['id_usuario_para'] = $almacen->id_usuario;
            Notificaciones::create($log);

            DB::commit();

            return $this->sendResponse($respuestasGuardadas, "Preguntas añadidas correctamente.");
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->sendError('Error al añadir la pregunta.', $th->getMessage(), 500);
        }
    }
    public function getRespuestasPorEncuesta(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tipo_encuesta' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Falta el tipo de encuesta.', $validator->errors());
            }

            $respuestas = DB::table('dc_respuestas_encuesta as re')
                ->select(
                    're.id as id_respuesta',
                    'e.pregunta',
                    'e.tipo_encuesta',
                    'e.tipo_pregunta',
                    're.id_canje',
                    DB::raw("CONCAT_WS(' ', sv.name, sv.first_last_name, sv.second_last_name) as nombre_completo"),
                    're.respuesta',
                    're.created_at as creacion_respuesta',
                )
                ->leftJoin('dc_encuestas as e', 're.id_pregunta', '=', 'e.id')
                ->leftJoin('swaps_view as sv', 're.id_canje', '=', 'sv.id')
                ->where('e.tipo_encuesta', 'LIKE', "%{$request->tipo_encuesta}%")
                ->groupBy(
                    're.id',
                    'e.pregunta',
                    'e.tipo_encuesta',
                    'e.tipo_pregunta',
                    're.id_canje',
                    'nombre_completo',
                    're.respuesta',
                    're.created_at',
                    'e.id'
                )
                ->orderBy('e.id', 'desc')
                ->get();

            return $this->sendResponse($respuestas, 'Respuestas de la encuesta cargadas correctamente');
        } catch (\Throwable $th) {
            return $this->sendError('Error al cargar las respuestas de la encuesta', $th, 500);
        }
    }
    public function getRespuestasPorCanje(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_canje' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Falta el id de canje o el tipo de encuesta.', $validator->errors());
            }

            $respuestas = DB::table('dc_respuestas_encuesta as re')
                ->select(
                    're.id as id_respuesta',
                    'e.pregunta',
                    'e.tipo_encuesta',
                    'e.tipo_pregunta',
                    're.id_canje',
                    'sv.name as nombre',
                    'sv.first_last_name as primer_apellido',
                    'sv.second_last_name as segundo_apellido',
                    're.respuesta',
                    're.created_at as creacion_respuesta',
                )
                ->leftJoin('dc_encuestas as e', 're.id_pregunta', '=', 'e.id')
                ->leftJoin('swaps_view as sv', 're.id_canje', '=', 'sv.id')
                ->where('re.id_canje', $request->id_canje)
                //->where('re.id_canje', '=', $request->id_canje)
                ->orderBy('e.id', 'desc')
                ->get();

            return $this->sendResponse($respuestas, 'Respuestas del canje cargadas correctamente');
        } catch (\Throwable $th) {
            return $this->sendError('Error al cargar las respuestas del canje', $th, 500);
        }
    }
    private function encriptarCorto($id)
    {
        $key = env('APP_KEY');
        $encoded = base64_encode($id . '|' . time());
        return rtrim(strtr($encoded, '+/', '-_'), '=');
    }
    private function desencriptarCorto($hash)
    {
        $decoded = base64_decode(strtr($hash, '-_', '+/'));
        [$id, $timestamp] = explode('|', $decoded);

        return $id;
    }
    private function enviarWhatsApp($canje, $tipo_encuesta = null)
    {
        try {

            $encuesta = str_replace('_', ' ', $tipo_encuesta);
            $canjeIdEncriptado = $this->encriptarCorto($canje->id);
            $urlva = config('app.url_frontend') . "/encuesta/{$tipo_encuesta}/{$canjeIdEncriptado}";

            $titulo = "¡Hola, {$canje->name}! Confirmamos que tu premio ha sido entregado con éxito. ✅";
            $mensaje = "Para asegurarnos de que todo salió perfecto, te invitamos a completar esta encuesta de {$encuesta}:\n\n" .
                "🔗 Link hacia la encuesta:\n{$urlva}\n" .
                "Tu opinión nos ayuda a mejorar y a seguir trayendo los mejores premios para ti. ¡Muchas gracias!";

            $this->whatsappService->sendMessage($canje->phone, $titulo);
            $this->whatsappService->sendMessage($canje->phone, $mensaje);

            return $this->sendResponse('Whatsapp enviado correctamente.');
        } catch (\Throwable $th) {
            return $this->sendError('Error al enviar el whatsapp', $th->getMessage(), 500);
        }
    }

    private function enviarCorreo($canje, $tipo_encuesta = null)
    {
        try {

            $encuesta = str_replace('_', ' ', $tipo_encuesta);
            $canjeData = (object) [
                'folio' => $canje->folio,
                'nombre_usuario' => $canje->name,
                'email' => $canje->email,
                'phone' => $canje->phone,
                'nombre_premio' => $canje->desc,
                'puntos_canjeados' => $canje->points_swap,
                'calle' => $canje->street,
                'numero_calle' => $canje->number,
                'colonia' => $canje->colony,
                'municipio' => $canje->municipality,
                'codigo_postal' => $canje->postal_code,
                'encuesta' => $encuesta,
            ];


            $canjeIdEncriptado = $this->encriptarCorto($canje->id);
            $urlva = config('app.url_frontend') . "/encuesta/{$tipo_encuesta}/{$canjeIdEncriptado}";

            Mail::to($canjeData->email)->send(new EnviarEncuesta($canjeData, $urlva));

            Log::info("Correo enviado correctamente");
        } catch (\Exception $e) {
            Log::error("Error al enviar correo: " . $e->getMessage());
        }
    }
}
