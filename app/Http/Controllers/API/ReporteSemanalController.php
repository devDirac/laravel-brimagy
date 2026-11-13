<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\Concerns\RespondeConServicios;
use App\Http\Requests\Reportes\ReporteSemanalAlmacenRequest;
use App\Services\ReporteSemanal\PeriodosReporte;
use App\Services\ReporteSemanal\ReporteSemanalAlmacenService;
use App\Services\ReporteSemanal\Excel\ReporteSemanalAlmacenExcel;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReporteSemanalController extends BaseController
{
    use RespondeConServicios;

    public function __construct(
        private readonly ReporteSemanalAlmacenService $reporte,
        private readonly PeriodosReporte $periodos,
        private readonly ReporteSemanalAlmacenExcel $excel,
    ) {}

    public function getReporteSemanalAlmacen(ReporteSemanalAlmacenRequest $request)
    {
        return $this->responder(
            fn() => $this->sendResponse($this->reporte->generar($request->filtros())),
            'Error al obtener el reporte semanal de almacén'
        );
    }

    /** Periodos de todas las plataformas */
    public function getPeriodosReporte()
    {
        return $this->responder(
            fn() => $this->sendResponse($this->periodos->listar()),
            'Error al obtener los periodos'
        );
    }

    /** Excel del periodo y la semana elegidos */
    public function exportarReporteSemanalAlmacenExcel(ReporteSemanalAlmacenRequest $request)
    {
        return $this->responder(function () use ($request) {
            $filtros = $request->filtros();
            $reporte = $this->reporte->generar($filtros);
            $writer = new Xlsx($this->excel->construir($reporte, $filtros->semana));

            return response()->streamDownload(
                fn() => $writer->save('php://output'),
                $this->excel->nombreArchivo($reporte, $filtros->semana),
                [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'max-age=0',
                ]
            );
        }, 'Error al generar el Excel del reporte semanal');
    }
}
