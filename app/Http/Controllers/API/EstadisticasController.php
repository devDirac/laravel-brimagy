<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\Inventario;
use App\Models\Kardex;
use App\Models\OrdenCompra;
use App\Models\Requisicion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EstadisticasController extends BaseController
{
    public $invalidFormatMessage = 'Formato invalido';

    public function getEstadisticasHome(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'periodo' => 'sometimes|in:semana,mes,año',
            ]);

            if ($validator->fails()) {
                return $this->sendError('El formato de datos no es válido.', $validator->errors());
            }
            //total participantes
            $participantes = User::get()->count();

            $resultado = [
                'participantes' => $participantes,
            ];

            return $this->sendResponse($resultado);
        } catch (\Throwable $th) {
            return $this->sendError('Error al obtener el dashboard de home', $th->getMessage(), 500);
        }
    }

}
