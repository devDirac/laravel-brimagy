<?php

namespace App\Services\Productos\Recursos;

use App\Models\Tallas;
use App\Services\Plataformas\PlataformaCatalogo;

class TallaProducto extends RecursoProducto
{
    public function claveRemota(): ?string { return PlataformaCatalogo::TALLAS; }
    public function modeloLocal(): string { return Tallas::class; }
    public function campoId(): string { return 'id_talla'; }
    public function campoIdRemoto(): ?string { return 'id_talla_brimagy'; }
    /** En la plataforma la columna se llama size. */
    public function campos(): array { return ['talla' => 'size']; }
    public function reglasCampos(): array { return ['talla' => 'required|string']; }
    public function campoUnico(): ?string { return 'talla'; }
    public function campoDescriptivo(): string { return 'talla'; }
    public function tablaBitacora(): string { return 'dc_tallas_premio'; }
    public function nombre(): string { return 'talla'; }
    public function esFemenino(): bool { return true; }
}
