<?php

namespace App\Http\Requests\Productos;

class CrearProductoRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'id_producto_brimagy' => 'nullable',
            'nombre_producto' => 'required|string',
            'descripcion' => 'nullable|string',
            'marca' => 'nullable|string',
            'sku' => 'nullable|string',
            'color' => 'nullable|string',
            'talla' => 'nullable|string',
            'costo_con_iva' => 'required|integer',
            'costo_puntos_con_iva' => 'required|integer',
            'envio_extra' => 'nullable|integer',
            'tipo_registro' => 'required|string',
            'tipo_producto' => 'required|string',
        ];
    }

    protected function mensajeError(): string
    {
        return 'El formato de datos no es válido.';
    }
}
