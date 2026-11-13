<?php

namespace App\Http\Requests\Reportes;

use App\Http\Requests\Productos\ApiFormRequest;
use App\Services\ReporteSemanal\FiltrosReporteSemanal;

class ReporteSemanalAlmacenRequest extends ApiFormRequest
{
    protected function mensajeError(): string
    {
        return 'El formato de datos no es válido.';
    }

    public function rules(): array
    {
        return [
            'id_periodo' => ['nullable', 'integer', 'exists:dc_periodos,id'],
            'semana' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function filtros(): FiltrosReporteSemanal
    {
        return FiltrosReporteSemanal::desdeRequest($this);
    }
}
