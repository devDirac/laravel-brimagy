<?php

namespace App\Services\ReporteSemanal\Excel;

use App\Services\ReporteSemanal\AgrupadorProveedores;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel del reporte semanal de almacén
 */
class ReporteSemanalAlmacenExcel
{
    /** Columnas: encabezado => [llave de la fila, tipo] */
    private const COLUMNAS = [
        'PREMIO' => ['nombre_producto', 'texto'],
        'PLATAFORMA' => ['plataforma', 'plataforma'],
        'SKU' => ['sku', 'texto'],
        'VALOR' => ['precio', 'moneda'],
        'PEDIDO' => ['cantidad_gestionada', 'numero'],
        'ENTREGA' => ['entrega', 'numero'],
        'FALTANTE' => ['faltante', 'numero'],
        'NOTAS' => ['notas', 'texto'],
        'FECHA DE FACTURA' => ['fecha_factura', 'fecha'],
        'MARCA' => ['marca', 'texto'],
        'P. UNITARIO' => ['precio_unitario_compra', 'moneda'],
        'TOTAL' => ['total', 'moneda'],
    ];

    /** Columnas que se suman en las filas de total */
    private const SUMABLES = ['cantidad_gestionada', 'entrega', 'faltante', 'total'];

    /** Colores de la celda de plataforma */
    private const COLORES_PLATAFORMA = [
        'puntotes' => ['fondo' => 'F7A8E3', 'texto' => '120515', 'etiqueta' => 'Puntotes'],
        'club bohn' => ['fondo' => '0277BD', 'texto' => 'FFFFFF', 'etiqueta' => 'Club Bohn'],
    ];

    private const FORMATO_MONEDA = '_-$* #,##0.00_-;[Red]$* #,##0.00;_-$* "-"??_-;_-@_-';

    private const COLOR_TITULO = '0D47A1';
    private const COLOR_ENCABEZADO = 'E1F5FE';
    private const COLOR_TOTAL_PROVEEDOR = 'E1F5FE';
    private const COLOR_TOTAL_SEMANA = 'C8E6C9';
    private const COLOR_TOTAL_GENERAL = 'FFCCBC';
    private const COLOR_TEXTO = '000000';

    /** Semana y proveedor sin fondo*/
    private const TAMANO_SEMANA = 16;
    private const TAMANO_PROVEEDOR = self::TAMANO_SEMANA - 2;

    public function __construct(private readonly AgrupadorProveedores $agrupador) {}

    public function construir(array $reporte, ?int $semana): Spreadsheet
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Reporte semanal almacén');

        $ultima = Coordinate::stringFromColumnIndex(count(self::COLUMNAS));
        $fila = $this->escribirTitulo($hoja, $ultima, $reporte, $semana);
        $semanas = $this->agrupador->porSemana($reporte);

        if (empty($semanas)) {
            $hoja->setCellValue("A{$fila}", 'SIN REGISTROS');
        }

        foreach ($semanas as $datosSemana) {
            $fila = $this->escribirSemana($hoja, $ultima, $datosSemana, $fila) + 1;
        }

        if (!empty($semanas)) {
            $this->escribirTotal($hoja, $ultima, $fila, 'TOTAL GENERAL', $reporte['totales'], self::COLOR_TOTAL_GENERAL, 13);
        }

        foreach (range(1, count(self::COLUMNAS)) as $i) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        return $libro;
    }

    public function nombreArchivo(array $reporte, ?int $semana): string
    {
        $sufijo = $semana ? "semana_{$semana}" : 'periodo';

        return "reporte_semanal_almacen_{$reporte['periodo']['fecha_inicio']}_{$sufijo}_" . now()->format('Ymd_His') . '.xlsx';
    }

    private function escribirTitulo(Worksheet $hoja, string $ultima, array $reporte, ?int $semana): int
    {
        $periodo = $reporte['periodo'];
        $alcance = 'TODO EL PERIODO';

        if ($semana) {
            $datosSemana = collect($reporte['semanas'])->firstWhere('numero', $semana);
            $alcance = $datosSemana ? $this->etiquetaSemana($datosSemana) : "SEMANA {$semana}";
        }

        $hoja->mergeCells("A1:{$ultima}1");
        $hoja->setCellValue('A1', 'REPORTE SEMANAL DE ALMACÉN');
        $this->rellenar($hoja, "A1:{$ultima}1", self::COLOR_TITULO, 'FFFFFF', 14);
        $hoja->getRowDimension(1)->setRowHeight(30);

        $hoja->mergeCells("A2:{$ultima}2");
        $hoja->setCellValue('A2', "PERIODO: {$this->rango($periodo['fecha_inicio'],$periodo['fecha_fin'])} · {$alcance}");
        $hoja->getStyle("A2:{$ultima}2")->getFont()->setBold(true);
        $hoja->getStyle("A2:{$ultima}2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return 4;
    }

    private function escribirSemana(Worksheet $hoja, string $ultima, array $datosSemana, int $fila): int
    {
        // SEMANA N · RANGO
        $hoja->mergeCells("A{$fila}:{$ultima}{$fila}");
        $hoja->setCellValue("A{$fila}", $this->etiquetaSemana($datosSemana['semana']));
        $this->textoSinFondo($hoja, "A{$fila}:{$ultima}{$fila}", self::TAMANO_SEMANA);
        $hoja->getRowDimension($fila)->setRowHeight(28);
        $fila++;

        // Encabezados de columnas
        $inicioTabla = $fila;
        $hoja->fromArray(array_keys(self::COLUMNAS), null, "A{$fila}");
        $this->rellenar($hoja, "A{$fila}:{$ultima}{$fila}", self::COLOR_ENCABEZADO, '000000', 14);
        $this->alinearColumnas($hoja, $fila, $fila);
        $fila++;

        foreach ($datosSemana['proveedores'] as $proveedor) {
            $fila = $this->escribirProveedor($hoja, $ultima, $proveedor, $fila);
        }

        $this->escribirTotal(
            $hoja,
            $ultima,
            $fila,
            'TOTAL ' . mb_strtoupper($datosSemana['semana']['etiqueta']),
            $datosSemana['totales'],
            self::COLOR_TOTAL_SEMANA,
            12
        );

        $hoja->getStyle("A{$inicioTabla}:{$ultima}{$fila}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        return $fila + 1;
    }

    /** Fila con el nombre del proveedor **/
    private function escribirProveedor(Worksheet $hoja, string $ultima, array $proveedor, int $fila): int
    {
        $hoja->mergeCells("A{$fila}:{$ultima}{$fila}");
        $hoja->setCellValue("A{$fila}", mb_strtoupper($proveedor['nombre_proveedor']));
        $this->textoSinFondo($hoja, "A{$fila}:{$ultima}{$fila}", self::TAMANO_PROVEEDOR);
        $hoja->getRowDimension($fila)->setRowHeight(24);
        $fila++;

        $inicio = $fila;
        foreach ($proveedor['registros'] as $registro) {
            $this->escribirRegistro($hoja, $registro, $fila);
            $fila++;
        }
        $this->formatoMoneda($hoja, $inicio, $fila - 1);
        $this->alinearColumnas($hoja, $inicio, $fila - 1);

        $this->escribirTotal(
            $hoja,
            $ultima,
            $fila,
            'TOTAL ', //mb_strtoupper($proveedor['nombre_proveedor'])
            $proveedor['totales'],
            self::COLOR_TOTAL_PROVEEDOR,
            14
        );

        return $fila + 1;
    }

    private function escribirRegistro(Worksheet $hoja, array $registro, int $fila): void
    {
        $columna = 1;
        foreach (self::COLUMNAS as [$llave, $tipo]) {
            $valor = $registro[$llave] ?? null;
            $celda = Coordinate::stringFromColumnIndex($columna++) . $fila;

            if ($valor === null || $valor === '') {
                continue;
            }

            if ($tipo === 'plataforma') {
                $this->celdaPlataforma($hoja, $celda, (string) $valor);
                continue;
            }

            if (!empty($registro['es_continuacion']) && $llave === 'cantidad_gestionada') {
                continue;
            }

            $hoja->setCellValue($celda, match ($tipo) {
                'moneda', 'numero' => (float) $valor,
                'fecha' => CarbonImmutable::parse($valor)->format('d-m-Y'),
                default => (string) $valor,
            });
        }
    }

    /** Celda de plataforma con el color de la plataforma. */
    private function celdaPlataforma(Worksheet $hoja, string $celda, string $plataforma): void
    {
        $clave = str_replace('_', ' ', mb_strtolower(trim($plataforma)));
        $estilo = self::COLORES_PLATAFORMA[$clave] ?? null;

        $hoja->setCellValue($celda, $estilo['etiqueta'] ?? mb_convert_case($plataforma, MB_CASE_TITLE));

        if ($estilo) {
            $this->rellenar($hoja, $celda, $estilo['fondo'], $estilo['texto'], 11);
        }
    }

    private function escribirTotal(Worksheet $hoja, string $ultima, int $fila, string $etiqueta, array $totales, string $color, int $tamano): void
    {
        $hoja->setCellValue("A{$fila}", $etiqueta);

        $columna = 1;
        foreach (self::COLUMNAS as [$llave]) {
            if (in_array($llave, self::SUMABLES, true)) {
                $hoja->setCellValue(Coordinate::stringFromColumnIndex($columna) . $fila, (float) ($totales[$llave] ?? 0));
            }
            $columna++;
        }

        $this->rellenar($hoja, "A{$fila}:{$ultima}{$fila}", $color, '000000', $tamano, Alignment::HORIZONTAL_LEFT);
        $this->formatoMoneda($hoja, $fila, $fila);
        $this->alinearColumnas($hoja, $fila, $fila);
        $hoja->getRowDimension($fila)->setRowHeight(22);
    }

    private function alinearColumnas(Worksheet $hoja, int $desde, int $hasta): void
    {
        if ($hasta < $desde) {
            return;
        }

        $columna = 1;
        foreach (self::COLUMNAS as [, $tipo]) {
            $alineacion = match ($tipo) {
                'moneda' => Alignment::HORIZONTAL_RIGHT,
                'numero', 'plataforma', 'fecha' => Alignment::HORIZONTAL_CENTER,
                default => null,
            };

            if ($alineacion) {
                $letra = Coordinate::stringFromColumnIndex($columna);
                $hoja->getStyle("{$letra}{$desde}:{$letra}{$hasta}")->getAlignment()->setHorizontal($alineacion);
            }
            $columna++;
        }
    }

    private function formatoMoneda(Worksheet $hoja, int $desde, int $hasta): void
    {
        if ($hasta < $desde) {
            return;
        }

        $columna = 1;
        foreach (self::COLUMNAS as [, $tipo]) {
            if ($tipo === 'moneda') {
                $letra = Coordinate::stringFromColumnIndex($columna);
                $hoja->getStyle("{$letra}{$desde}:{$letra}{$hasta}")->getNumberFormat()->setFormatCode(self::FORMATO_MONEDA);
            }
            $columna++;
        }
    }

    private function rellenar(
        Worksheet $hoja,
        string $rango,
        string $fondo,
        string $texto,
        int $tamano,
        string $alineacion = Alignment::HORIZONTAL_CENTER
    ): void {
        $estilo = $hoja->getStyle($rango);
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fondo);
        $estilo->getFont()->setBold(true)->setSize($tamano)->getColor()->setRGB($texto);
        $estilo->getAlignment()
            ->setHorizontal($alineacion)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    /** Texto en negritas y negro, sin fondo */
    private function textoSinFondo(Worksheet $hoja, string $rango, int $tamano): void
    {
        $estilo = $hoja->getStyle($rango);
        $estilo->getFont()->setBold(true)->setSize($tamano)->getColor()->setRGB(self::COLOR_TEXTO);
        $estilo->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    /** "SEMANA 1 · 01-12-2025 AL 07-12-2025" */
    private function etiquetaSemana(array $semana): string
    {
        return mb_strtoupper("{$semana['etiqueta']} · {$this->rango($semana['fecha_inicio'],$semana['fecha_fin'])}");
    }

    private function rango(string $inicio, string $fin): string
    {
        return CarbonImmutable::parse($inicio)->format('d-m-Y') . ' AL ' . CarbonImmutable::parse($fin)->format('d-m-Y');
    }
}
