<?php

namespace App\Services\Productos\Recursos;

use App\Models\Colores;
use App\Services\Plataformas\PlataformaCatalogo;

class ColorProducto extends RecursoProducto
{
    public function claveRemota(): ?string { return PlataformaCatalogo::COLORES; }
    public function modeloLocal(): string { return Colores::class; }
    public function campoId(): string { return 'id_color'; }
    public function campoIdRemoto(): ?string { return 'id_color_brimagy'; }
    public function campos(): array { return ['color' => 'color']; }
    public function reglasCampos(): array { return ['color' => 'required|string']; }
    public function campoUnico(): ?string { return 'color'; }
    public function campoDescriptivo(): string { return 'color'; }
    public function tablaBitacora(): string { return 'dc_colores_premio'; }
    public function nombre(): string { return 'color'; }
    public function esFemenino(): bool { return false; }
}
