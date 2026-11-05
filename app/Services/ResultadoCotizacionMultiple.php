<?php

namespace App\Services;

class ResultadoCotizacionMultiple
{
    public function __construct(
        public string $modo,
        public int $validacionesCreadas,
        public int $ordenesCreadas,
        public string $mensaje,
    ) {
    }

    public function toArray(): array
    {
        return [
            'modo' => $this->modo,
            'validaciones_creadas' => $this->validacionesCreadas,
            'ordenes_creadas' => $this->ordenesCreadas,
        ];
    }
}