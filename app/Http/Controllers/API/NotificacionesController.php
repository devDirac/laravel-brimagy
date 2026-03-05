<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Notificaciones;
use Illuminate\Support\Facades\Auth;

class NotificacionesController extends BaseController
{

    public function setNotificacion(Request $request)
    {
        try {
            $input = $request->all();
            $validator = Validator::make($input, [
                'detalle' => 'required',
                'id_tipo_notificacion' => 'required',
                'id_usuario_para' => 'required'
            ]);
            if ($validator->fails()) {
                return $this->sendError('Todos los valores son requeridos', $validator->errors(), 500);
            }
            $user = Auth::user();
            $log['vista'] = 0;
            $log['id_tipo_notificacion'] = $request->id_tipo_notificacion;
            $log['id_usuario_creador'] = $user->id;
            $log['id_usuario_para'] = $request->id_usuario_para;
            Notificaciones::create($log);
            return $this->sendResponse("La notificación fue creada con éxito");
        } catch (\Throwable $th) {
            return $this->sendError('Error', $th, 500);
        }
    }

    public function removeNotificacion(Request $request)
    {
        try {
            $input = $request->all();
            $validator = Validator::make($input, [
                'id' => 'required'
            ]);
            if ($validator->fails()) {
                return $this->sendError('El id es requerido', $validator->errors(), 500);
            }
            $notificacion = Notificaciones::find($request->id);
            if (!$notificacion) {
                return $this->sendError('La notificación indicada no existe', [], 404);
            }
            $notificacion->vista_fecha = now();
            $notificacion->vista = 1;
            $notificacion->save();
            return $this->sendResponse("La notificación fue actualizada con éxito");
        } catch (\Throwable $th) {
            return $this->sendError('Error', $th, 500);
        }
    }


    public function getNotificacionesPorUsuario(Request $request)
    {
        try {
            $input = $request->all();
            $validator = Validator::make($input, [
                'id_usuario_para' => 'required'
            ]);
            if ($validator->fails()) {
                return $this->sendError('El id es requerido', $validator->errors(), 500);
            }
            $notificaciones = DB::table('dc_notificaciones as n')
                ->select(
                    'n.*',
                    'tn.tipo_notificacion',
                    'tn.descripcion',
                )
                ->join('dc_tipo_notificacion as tn', 'n.id_tipo_notificacion', '=', 'tn.id')
                ->where('n.id_usuario_para', $request->id_usuario_para)
                ->get();

            return $this->sendResponse($notificaciones);
        } catch (\Throwable $th) {
            return $this->sendError('Error', $th, 500);
        }
    }
}
