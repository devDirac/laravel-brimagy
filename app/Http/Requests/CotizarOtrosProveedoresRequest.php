<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CotizarOtrosProveedoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plataforma' => 'nullable|string',
            'asignaciones' => 'required|array|min:1',
            'asignaciones.*.id_validacion' => 'required|integer|exists:dc_validacion_canje,id',
            'asignaciones.*.proveedores' => 'required|array|min:1',
            'asignaciones.*.proveedores.*' => 'integer|exists:dc_catalogo_proveedores,id',
            'id_usuario' => 'nullable|integer|exists:dc_usuarios_plataforma,id',
        ];
    }

    /** @return array<int, int[]> */
    public function asignacionesNormalizadas(): array
    {
        return collect($this->input('asignaciones'))
            ->mapWithKeys(fn($a) => [(int) $a['id_validacion'] => array_map('intval', $a['proveedores'])])
            ->all();
    }
}