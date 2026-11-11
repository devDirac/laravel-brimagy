<?php

namespace App\Http\Requests\Canjes;

use App\Http\Requests\Productos\ApiFormRequest;
use App\Services\Canjes\EstatusCanje;
use App\Services\Canjes\FiltrosCanjes;
use Illuminate\Validation\Rule;

class CanjesPorEstatusRequest extends ApiFormRequest
{
    protected function mensajeError(): string
    {
        return 'El formato de datos no es válido.';
    }

    public function rules(): array
    {
        return [
            'estatus' => ['required', Rule::enum(EstatusCanje::class)],
            'plataforma' => ['nullable', Rule::in(['puntotes', 'club_bohn'])],
            'tipo_producto' => ['nullable', Rule::in(['fisico', 'digital'])],
            'search' => ['nullable', 'string', 'max:255'],
            'fecha1' => ['nullable', 'date'],
            'fecha2' => ['nullable', 'date'],
        ];
    }

    public function estatus(): EstatusCanje
    {
        return EstatusCanje::from($this->validated('estatus'));
    }

    public function filtros(): FiltrosCanjes
    {
        return FiltrosCanjes::desdeRequest($this);
    }
}