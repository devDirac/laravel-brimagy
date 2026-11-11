<?php

namespace App\Services\Canjes;

/**
 * Grupos de estatus que muestra el contador de canjes.
 * Es el único lugar donde vive la regla "qué estatus de validación cae en qué grupo",
 * así el contador y la tabla del modal nunca pueden dar números distintos.
 */
enum EstatusCanje: string
{
    case Total = 'total';
    case SinValidar = 'sin_validar';
    case EnProceso = 'en_proceso';
    case Validados = 'validados';

    public const ESTATUS_VALIDADO = 'identidad_validada';

    /** Grupo al que pertenece un estatus de dc_validacion_canje (null = sin validar). */
    public static function desdeValidacion(?string $estatusValidacion): self
    {
        $estatus = strtolower(trim((string) $estatusValidacion));

        return match (true) {
            $estatus === '' => self::SinValidar,
            $estatus === self::ESTATUS_VALIDADO => self::Validados,
            default => self::EnProceso,
        };
    }

    public function incluye(?string $estatusValidacion): bool
    {
        return $this === self::Total || self::desdeValidacion($estatusValidacion) === $this;
    }
}