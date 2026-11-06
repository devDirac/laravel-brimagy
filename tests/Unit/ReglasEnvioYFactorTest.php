<?php

namespace Tests\Unit;

use App\Services\Productos\ReglasEnvioYFactor;
use PHPUnit\Framework\TestCase;

class ReglasEnvioYFactorTest extends TestCase
{
    const POR_DEFECTO = ['envio_extra' => 0, 'valor_factor' => 15];

    private $reglas;
    private $variables;

    protected function setUp(): void
    {
        $this->reglas = new ReglasEnvioYFactor();
        $this->variables = (object) ['envio_extra' => 40, 'factor' => 1.2];
    }

    private function assertResultado(array $resultado, $envio, $factor): void
    {
        $this->assertEquals($envio, $resultado['envio_extra']);
        $this->assertEquals($factor, $resultado['valor_factor']);
    }

    public function test_excel_usa_sus_valores_si_vienen(): void
    {
        $this->assertResultado(
            $this->reglas->paraExcel(['envio_extra' => '25', 'valor_factor' => '1.5'], $this->variables, self::POR_DEFECTO),
            25, 1.5
        );
    }

    public function test_excel_en_cero_o_vacio_toma_variables(): void
    {
        $this->assertResultado(
            $this->reglas->paraExcel(['envio_extra' => '0', 'valor_factor' => ''], $this->variables, self::POR_DEFECTO),
            40, 1.2
        );
    }

    public function test_excel_sin_variables_toma_por_defecto(): void
    {
        $this->assertResultado(
            $this->reglas->paraExcel(['envio_extra' => '0', 'valor_factor' => '0'], null, self::POR_DEFECTO),
            0, 15
        );
    }

    public function test_individual_sin_envio_toma_variables_y_factor_de_variables(): void
    {
        $this->assertResultado(
            $this->reglas->paraIndividual(['envio_extra' => null], $this->variables, self::POR_DEFECTO),
            40, 1.2
        );
    }

    public function test_individual_sin_variables_usa_factor_15(): void
    {
        $this->assertResultado($this->reglas->paraIndividual([], null, self::POR_DEFECTO), 0, 15);
    }

    public function test_editar_respeta_lo_recibido_aunque_sea_cero(): void
    {
        $producto = (object) ['envio_extra' => 5, 'valor_factor' => 3];
        $this->assertResultado(
            $this->reglas->paraEdicion(['envio_extra' => '0', 'valor_factor' => '0'], $producto, $this->variables, self::POR_DEFECTO),
            0, 0
        );
    }

    public function test_editar_sin_valor_conserva_el_del_producto(): void
    {
        $producto = (object) ['envio_extra' => 5, 'valor_factor' => 3];
        $this->assertResultado($this->reglas->paraEdicion([], $producto, $this->variables, self::POR_DEFECTO), 5, 3);
    }

    public function test_editar_sin_valor_ni_registro_toma_variables_y_luego_por_defecto(): void
    {
        $producto = (object) ['envio_extra' => null, 'valor_factor' => null];

        $this->assertResultado($this->reglas->paraEdicion([], $producto, $this->variables, self::POR_DEFECTO), 40, 1.2);
        $this->assertResultado($this->reglas->paraEdicion([], $producto, null, self::POR_DEFECTO), 0, 15);
    }
}
