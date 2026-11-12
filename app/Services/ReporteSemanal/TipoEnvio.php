<?php

namespace App\Services\ReporteSemanal;

enum TipoEnvio: int
{
    case Proveedor = 1;
    case Brimagy = 2;

    public function nota(): string
    {
        return match ($this) {
            self::Proveedor => 'Envío por proveedor',
            self::Brimagy => 'Envío por Brimagy',
        };
    }

    public static function notaDe($valor): ?string
    {
        return $valor === null ? null : self::tryFrom((int) $valor)?->nota();
    }
}
