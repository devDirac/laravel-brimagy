<?php

namespace App\Http\Requests\Reportes;

use App\Http\Requests\Productos\ApiFormRequest;
use App\Services\ReporteSemanal\FiltrosReporteSemanal;
use Illuminate\Validation\Rule;

class ReporteSemanalAlmacenRequest extends ApiFormRequest
{
    protected function mensajeError(): string
    {
        return 'El formato de datos no es válido.';
    }

    public function rules(): array
    {
        return [
            'plataforma' => ['required', Rule::in(['puntotes', 'club_bohn'])],
            'id_periodo' => ['nullable', 'integer', 'exists:dc_periodos,id'],
            'semana' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function filtros(): FiltrosReporteSemanal
    {
        return FiltrosReporteSemanal::desdeRequest($this);
    }
}
