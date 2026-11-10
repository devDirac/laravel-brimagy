<?php

namespace App\Services\Productos;

/**
 * Fórmula cálculo para puntos y para los datos del producto.
 */
class CalculadoraPuntos
{
    const IVA = 1.16;

    /**
     * Las llaves del resultado coinciden con las columnas de dc_catalogo_productos.
     *
     * @param mixed $costoConIva        Costo del proveedor con IVA
     * @param mixed $costoPuntosConIva  Costo en puntos con IVA
     * @param mixed $feeBrimagy         Porcentaje (ej. 12 = 12 %)
     * @param mixed $envioBase
     * @param mixed $costoCaja
     * @param mixed $envioExtra
     * @param mixed $valorFactor        0 o vacío = sin factor
     */
    public function calcular(
        $costoConIva,
        $costoPuntosConIva,
        $feeBrimagy,
        $envioBase,
        $costoCaja,
        $envioExtra,
        $valorFactor
    ): array {
        $costoConIva = $this->numero($costoConIva);
        $costoPuntosConIva = $this->numero($costoPuntosConIva);
        $feeBrimagy = $this->numero($feeBrimagy);
        $envioBase = $this->numero($envioBase);
        $costoCaja = $this->numero($costoCaja);
        $envioExtra = $this->numero($envioExtra);
        $valorFactor = $this->numero($valorFactor);

        $costoSinIva = $costoConIva > 0 ? $costoConIva / self::IVA : 0;
        $costoPuntosSinIva = $costoPuntosConIva > 0 ? $costoPuntosConIva / self::IVA : 0;

        $subtotal = $costoPuntosSinIva + $costoPuntosSinIva * ($feeBrimagy / 100);
        $totalEnvio = $envioBase + $costoCaja + $envioExtra;
        $total = $subtotal + $totalEnvio;

        $puntos = (((int) $total) % 2 !== 0) ? $total + 2 : $total + 1;
        $factor = $valorFactor == 0 ? round($total) : round($total * $valorFactor);

        return [
            'costo_con_iva' => $costoConIva,
            'costo_sin_iva' => $costoSinIva,
            'costo_puntos_con_iva' => $costoPuntosConIva,
            'costo_puntos_sin_iva' => $costoPuntosSinIva,
            'fee_brimagy' => $feeBrimagy,
            'envio_base' => $envioBase,
            'costo_caja' => $costoCaja,
            'envio_extra' => $envioExtra,
            'subtotal' => $subtotal,
            'total_envio' => $totalEnvio,
            'total' => $total,
            'puntos' => $puntos,
            'valor_factor' => $valorFactor,
            'factor' => $factor,
        ];
    }

    /** Campos del cálculo que se registran en la bitácora cuando cambian. */
    public static function camposAuditables(): array
    {
        return [
            'fee_brimagy',
            'envio_base',
            'costo_caja',
            'envio_extra',
            'subtotal',
            'total_envio',
            'total',
            'puntos',
            'valor_factor',
            'factor',
            'costo_con_iva',
            'costo_sin_iva',
            'costo_puntos_con_iva',
            'costo_puntos_sin_iva',
        ];
    }

    /** '', null, 'null' o texto no numérico cuentan como 0 */
    private function numero($valor): float
    {
        return is_numeric($valor) ? (float) $valor : 0.0;
    }
}
