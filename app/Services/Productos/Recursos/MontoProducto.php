<?php

namespace App\Services\Productos\Recursos;

use App\Models\Montos;
use App\Services\Plataformas\PlataformaCatalogo;

/** Montos de productos digitales. */
class MontoProducto extends RecursoProducto
{
    public function claveRemota(): ?string { return PlataformaCatalogo::MONTOS; }
    public function modeloLocal(): string { return Montos::class; }
    public function campoId(): string { return 'id_monto'; }
    public function campoIdRemoto(): ?string { return 'id_monto_brimagy'; }
    public function campos(): array
    {
        return ['monto' => 'monto', 'puntos' => 'points', 'descripcion' => 'descripcion'];
    }
    public function reglasCampos(): array
    {
        return [
            'monto' => 'required|string',
            'puntos' => 'required|integer',
            'descripcion' => 'nullable|string',
        ];
    }
    public function campoUnico(): ?string { return 'monto'; }
    public function campoDescriptivo(): string { return 'monto'; }
    public function tablaBitacora(): string { return 'dc_montos_digital'; }
    public function nombre(): string { return 'monto'; }
    public function esFemenino(): bool { return false; }
}
