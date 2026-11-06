<?php

namespace App\Http\Requests\Productos;

class EditarProductoRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'id_producto' => 'required|integer|exists:dc_catalogo_productos,id',
        ];
    }

    protected function mensajeError(): string
    {
        return 'Falta el id del producto.';
    }
}
