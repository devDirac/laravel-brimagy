<?php

namespace App\Http\Requests\Productos;

use App\Http\Controllers\API\BaseController;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiFormRequest extends FormRequest
{
    /** Mensaje principal del error de validación. */
    abstract protected function mensajeError(): string;

    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            app(BaseController::class)->sendError($this->mensajeError(), $validator->errors())
        );
    }
}
