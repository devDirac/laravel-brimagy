<?php

namespace Tests\Unit;

use App\Services\Productos\CalculadoraPuntos;
use PHPUnit\Framework\TestCase;

class CalculadoraPuntosTest extends TestCase
{
    /** Formula para datos de puntos y valores generales del producto */
    private function formulaOriginal($costoConIva, $costoPuntosConIva, $fee, $envioBase, $costoCaja, $envioExtra, $valorFactor): array
    {
        $costo_proveedor_sin_iva = $costoConIva / 1.16;
        $costo_puntos_sin_iva = $costoPuntosConIva / 1.16;
        $porcentaje = (float) $fee / 100;
        $valor_con_fee = (float) $costo_puntos_sin_iva * $porcentaje;
        $subtotal = $costo_puntos_sin_iva + $valor_con_fee;
        $total_envio = $envioBase + $costoCaja + $envioExtra;
        $total = $subtotal + $total_envio;
        $redondeo = (((int) $total) % 2 !== 0) ? $total + 2 : $total + 1;
        $puntos = $valorFactor == 0 ? round($total) : round($total * $valorFactor);

        return [
            'costo_sin_iva' => $costo_proveedor_sin_iva,
            'costo_puntos_sin_iva' => $costo_puntos_sin_iva,
            'subtotal' => $subtotal,
            'total_envio' => $total_envio,
            'total' => $total,
            'puntos' => $redondeo,
            'factor' => $puntos,
        ];
    }

    public function casos(): array
    {
        return [
            'puntotes sin factor' => [1500, 1200, 12, 180, 19, 0, 0],
            'club bohn con factor' => [2999, 2500, 15, 180, 19, 50, 1.25],
            'factor vacío (null)' => [800, 750, 12, 180, 19, 0, null],
            'envío extra como texto' => ['1000', '980', '12', '180', '19', '35', '1.1'],
            'costo cero' => [0, 0, 12, 180, 19, 0, 0],
            'total impar' => [116, 116, 0, 0, 0, 0, 0],
        ];
    }

    /** @dataProvider casos */
    public function test_da_los_mismos_numeros_que_la_formula_original(...$entrada): void
    {
        $esperado = $this->formulaOriginal(...$entrada);
        $resultado = (new CalculadoraPuntos())->calcular(...$entrada);

        foreach ($esperado as $campo => $valor) {
            $this->assertEqualsWithDelta($valor, $resultado[$campo], 0.000001, "Campo {$campo}");
        }
    }

    public function test_regresa_todas_las_columnas_auditables(): void
    {
        $resultado = (new CalculadoraPuntos())->calcular(1000, 900, 12, 180, 19, 0, 0);

        foreach (CalculadoraPuntos::camposAuditables() as $campo) {
            $this->assertArrayHasKey($campo, $resultado);
        }
    }
}
