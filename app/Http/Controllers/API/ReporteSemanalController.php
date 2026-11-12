<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Concerns\RespondeConServicios;
use App\Http\Requests\Reportes\ReporteSemanalAlmacenRequest;
use App\Services\ReporteSemanal\ReporteSemanalAlmacenService;

class ReporteSemanalController extends BaseController
{
    use RespondeConServicios;

    public function __construct(private readonly ReporteSemanalAlmacenService $reporte) {}

    public function getReporteSemanalAlmacen(ReporteSemanalAlmacenRequest $request)
    {
        return $this->responder(
            fn() => $this->sendResponse($this->reporte->generar($request->filtros())),
            'Error al obtener el reporte semanal de almacén'
        );
    }
}
