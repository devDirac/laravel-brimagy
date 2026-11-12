<?php

namespace App\Services\ReporteSemanal;

use App\Models\Periodo;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Periodo registrado en dc_periodos dividido en semanas.
 * El número de semana se calcula con el helper calcularNumSemana()
 */
final class PeriodoSemanal
{
    private const DIAS_SEMANA = 7;

    public function __construct(
        public readonly int $id,
        public readonly int $idPlataforma,
        public readonly CarbonImmutable $inicio,
        public readonly CarbonImmutable $fin,
        public readonly bool $activo,
    ) {}

    public static function desdeModelo(Periodo $periodo): self
    {
        return new self(
            id: (int) $periodo->id,
            idPlataforma: (int) $periodo->id_plataforma,
            inicio: CarbonImmutable::parse($periodo->fecha_inicio)->startOfDay(),
            fin: CarbonImmutable::parse($periodo->fecha_fin)->startOfDay(),
            activo: (int) $periodo->activo === 1,
        );
    }

    /** @return Semana[] todas las semanas del periodo; la última puede ser más corta. */
    public function semanas(): array
    {
        $semanas = [];
        $inicio = $this->inicio;
        $numero = 1;

        while ($inicio->lte($this->fin)) {
            $fin = $inicio->addDays(self::DIAS_SEMANA - 1);
            $semanas[] = new Semana($numero++, $inicio, $fin->gt($this->fin) ? $this->fin : $fin);
            $inicio = $inicio->addDays(self::DIAS_SEMANA);
        }

        return $semanas;
    }

    /** Número de semana de una fecha, o null si cae fuera del periodo. */
    public function numeroSemana($fecha): ?int
    {
        try {
            return calcularNumSemana($this->inicio, $this->fin, $fecha);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'id_plataforma' => $this->idPlataforma,
            'fecha_inicio' => $this->inicio->toDateString(),
            'fecha_fin' => $this->fin->toDateString(),
            'activo' => $this->activo,
            'total_semanas' => count($this->semanas()),
        ];
    }
}
