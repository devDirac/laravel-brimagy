<?php

namespace App\Services\ReporteSemanal;

use Carbon\CarbonImmutable;

/** Una semana dentro de un periodo */
final class Semana
{
    public function __construct(
        public readonly int $numero,
        public readonly CarbonImmutable $inicio,
        public readonly CarbonImmutable $fin,
    ) {}

    public function toArray(): array
    {
        return [
            'numero' => $this->numero,
            'etiqueta' => "Semana {$this->numero}",
            'fecha_inicio' => $this->inicio->toDateString(),
            'fecha_fin' => $this->fin->toDateString(),
        ];
    }
}
