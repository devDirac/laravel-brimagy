<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseController as BaseController;
use App\Services\ReporteCanjesService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ReportesExcel extends BaseController
{
    public function __construct(private ReporteCanjesService $reporteCanjesService)
    {
    }

    public function exportarExcel(Request $request)
    {
        try {
            $data = $this->reporteCanjesService->obtenerReporte($request);

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Reporte Canjes');

            $gruposHeader = [
                ['rango' => 'A1:L1', 'texto' => 'Información de Usuario', 'color' => '0D47A1'], // azul
                ['rango' => 'M1:Y1', 'texto' => 'Información de canje', 'color' => '0288D1'], // celeste fuerte
                ['rango' => 'Z1:AC1', 'texto' => 'Información de compra', 'color' => '4DB6AC'], // verde
                ['rango' => 'AD1:AU1', 'texto' => 'Información Financiera', 'color' => '29B6F6'], // celeste
            ];

            foreach ($gruposHeader as $grupo) {
                $sheet->mergeCells($grupo['rango']);

                $primeraCelda = explode(':', $grupo['rango'])[0];
                $sheet->setCellValue($primeraCelda, $grupo['texto']);

                $sheet->getStyle($grupo['rango'])->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($grupo['color']);

                $sheet->getStyle($grupo['rango'])->getFont()
                    ->setBold(true)
                    ->setSize(14)
                    ->getColor()->setRGB('FFFFFF');

                $sheet->getStyle($grupo['rango'])->getFont()->setBold(true);
                $sheet->getStyle($grupo['rango'])->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            }

            $sheet->getRowDimension(1)->setRowHeight(30);

            $headers = [
                'API ID',
                'Nombre',
                'Teléfono',
                'Correo',
                'Calle',
                'No. Ext',
                'No. Int',
                'Colonia',
                'Municipio/Delegación',
                'C.P.',
                'Entre calles',
                'Referencia adicional',
                'Folio canje',
                'Categoría',
                'Subcategoría',
                'Premio',
                'SKU',
                'Talla',
                'Color',
                'Puntos premio',
                'Premios canjeados',
                'Puntos canjeados',
                'Fecha canje',
                'Mes',
                'Semana',
                'Fecha compra',
                'Factura',
                'Marca',
                'Imei',
                'Precio S/IVA puntos presupuestado',
                'Precio S/IVA cliente presupuestado',
                'Precio compra S/IVA',
                'Diferencia precio usuario',
                'Diferencia precio cliente',
                'Fee puntos presupuestado',
                'Fee cliente presupuestado',
                'Fee real',
                'Diferencia precio usuario 2',
                'Diferencia precio cliente 2',
                'Envío presupuestado',
                'Costo envío real',
                'Diferencia envío',
                'Total puntos presupuestado',
                'Total cliente presupuestado',
                'Total real',
                'Total diferencia usuario',
                'Total diferencia cliente',
            ];

            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '2', $header);
                $col++;
            }

            $sheet->getRowDimension(2)->setRowHeight(22);
            $sheet->freezePane('A3');

            $ultimaColumnaHeaders = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '2';
            $sheet->getStyle('A2:' . $ultimaColumnaHeaders)->getFont()->setBold(true);
            $sheet->getStyle('A2:' . $ultimaColumnaHeaders)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9D9D9');
            $sheet->getStyle('A2:' . $ultimaColumnaHeaders)->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                ->setWrapText(true);

            $row = 3;
            foreach ($data['detalle'] as $item) {
                $item = (array) $item;

                $sheet->setCellValue('A' . $row, $item['api_id'] ?? '');
                $sheet->setCellValue('B' . $row, $item['nombre'] ?? '');
                $sheet->setCellValue('C' . $row, $item['telefono'] ?? '');
                $sheet->setCellValue('D' . $row, $item['correo'] ?? '');
                $sheet->setCellValue('E' . $row, $item['calle'] ?? '');
                $sheet->setCellValue('F' . $row, $item['no_ext'] ?? '');
                $sheet->setCellValue('G' . $row, $item['no_int'] ?? '');
                $sheet->setCellValue('H' . $row, $item['colonia'] ?? '');
                $sheet->setCellValue('I' . $row, $item['municipio_delegacion'] ?? '');
                $sheet->setCellValue('J' . $row, $item['codigo_postal'] ?? '');
                $sheet->setCellValue('K' . $row, $item['entre_calles'] ?? '');
                $sheet->setCellValue('L' . $row, $item['referencia_adicional'] ?? '');
                $sheet->setCellValue('M' . $row, $item['folio_canje'] ?? '');
                $sheet->setCellValue('N' . $row, $item['categoria'] ?? '');
                $sheet->setCellValue('O' . $row, $item['subcategoria'] ?? '');
                $sheet->setCellValue('P' . $row, $item['premio'] ?? '');
                $sheet->setCellValue('Q' . $row, $item['sku'] ?? '');
                $sheet->setCellValue('R' . $row, $item['talla'] ?? '');
                $sheet->setCellValue('S' . $row, $item['color'] ?? '');
                $sheet->setCellValue('T' . $row, $item['puntos_premio'] ?? '');
                $sheet->setCellValue('U' . $row, $item['premios_canjeados'] ?? '');
                $sheet->setCellValue('V' . $row, $item['puntos_canjeados'] ?? '');
                $sheet->setCellValue('W' . $row, $item['fecha_canje'] ?? '');
                $sheet->setCellValue('X' . $row, $item['mes_periodo'] ?? '');
                $sheet->setCellValue('Y' . $row, $item['semana_periodo'] ?? '');
                $sheet->setCellValue('Z' . $row, $item['fecha_compra'] ?? '');
                $sheet->setCellValue('AA' . $row, $item['folio_factura'] ?? '');
                $sheet->setCellValue('AB' . $row, $item['marca'] ?? '');
                $sheet->setCellValue('AC' . $row, $item['imei'] ?? '');
                $sheet->setCellValue('AD' . $row, $item['precio_sin_iva_puntos_prespuestado'] ?? '');
                $sheet->setCellValue('AE' . $row, $item['precio_sin_iva_proveedor_prespuestado'] ?? '');
                $sheet->setCellValue('AF' . $row, $item['precio_compra_sin_iva'] ?? '');
                $sheet->setCellValue('AG' . $row, $item['diferencia_precio_usuario'] ?? '');
                $sheet->setCellValue('AH' . $row, $item['diferencia_precio_proveedor'] ?? '');
                $sheet->setCellValue('AJ' . $row, $item['fee_presupuestado_puntos'] ?? '');
                $sheet->setCellValue('AI' . $row, $item['fee_presupuestado_proveedor_puntos'] ?? '');
                $sheet->setCellValue('AK' . $row, $item['fee_real'] ?? '');
                $sheet->setCellValue('AL' . $row, $item['diferencia_precio_usuario_2'] ?? '');
                $sheet->setCellValue('AM' . $row, $item['diferencia_precio_proveedor_2'] ?? '');
                $sheet->setCellValue('AN' . $row, $item['envio_presupuestado'] ?? '');
                $sheet->setCellValue('AO' . $row, $item['costo_envio_real'] ?? '');
                $sheet->setCellValue('AP' . $row, $item['diferencia_envio'] ?? '');
                $sheet->setCellValue('AQ' . $row, $item['total_presupuestado_puntos'] ?? '');
                $sheet->setCellValue('AR' . $row, $item['total_presupuestado_proveedor_puntos'] ?? '');
                $sheet->setCellValue('AS' . $row, $item['total_real'] ?? '');
                $sheet->setCellValue('AT' . $row, $item['total_diferencia_puntos_usuario'] ?? '');
                $sheet->setCellValue('AU' . $row, $item['total_diferencia_puntos_proveedor'] ?? '');
                $row++;
            }

            $ultimaFilaDatos = $row - 1;
            $datosGlobalesTitulo = $row + 1;
            $datosGlobalesPorcentaje = $row + 2;

            $sheet->getStyle('A2:AC2')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E1F5FE');
            $sheet->getStyle('AD2:AH2')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('C8E6C9');
            $sheet->getStyle('AI2:AM2')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E0E0E0');
            $sheet->getStyle('AN2:AP2')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFE0B2');
            $sheet->getStyle('AQ2:AU2')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFCCBC');

            //$row++;
            $sheet->setCellValue('A' . $row, 'Total');
            $sheet->setCellValue('AD' . $row, $data['totales']['precio_sin_iva_puntos_prespuestado'] ?? '');
            $sheet->setCellValue('AE' . $row, $data['totales']['precio_sin_iva_proveedor_prespuestado'] ?? '');
            $sheet->setCellValue('AF' . $row, $data['totales']['precio_compra_sin_iva'] ?? '');
            $sheet->setCellValue('AG' . $row, $data['totales']['diferencia_precio_usuario'] ?? '');
            $sheet->setCellValue('AH' . $row, $data['totales']['diferencia_precio_proveedor'] ?? '');
            $sheet->setCellValue('AI' . $row, $data['totales']['fee_presupuestado_puntos'] ?? '');
            $sheet->setCellValue('AJ' . $row, $data['totales']['fee_presupuestado_proveedor_puntos'] ?? '');
            $sheet->setCellValue('AK' . $row, $data['totales']['fee_real'] ?? '');
            $sheet->setCellValue('AL' . $row, $data['totales']['diferencia_precio_usuario_2'] ?? '');
            $sheet->setCellValue('AM' . $row, $data['totales']['diferencia_precio_proveedor_2'] ?? '');
            $sheet->setCellValue('AN' . $row, $data['totales']['envio_presupuestado'] ?? '');
            $sheet->setCellValue('AO' . $row, $data['totales']['costo_envio_real'] ?? '');
            $sheet->setCellValue('AP' . $row, $data['totales']['diferencia_envio'] ?? '');
            $sheet->setCellValue('AQ' . $row, $data['totales']['total_presupuestado_puntos'] ?? '');
            $sheet->setCellValue('AR' . $row, $data['totales']['total_presupuestado_proveedor_puntos'] ?? '');
            $sheet->setCellValue('AS' . $row, $data['totales']['total_real'] ?? '');
            $sheet->setCellValue('AT' . $row, $data['totales']['total_diferencia_puntos_usuario'] ?? '');
            $sheet->setCellValue('AU' . $row, $data['totales']['total_diferencia_puntos_proveedor'] ?? '');

            $sheet->getStyle('A' . $row . ':AU' . $row)->getFont()->setSize(14)->setBold(true);
            $sheet->getStyle('A' . $row . ':AU' . $row)->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                ->setWrapText(true);

            $sheet->getStyle('A' . $row . ':AC' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E1F5FE');
            $sheet->getStyle('AD' . $row . ':AH' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('C8E6C9');
            $sheet->getStyle('AI' . $row . ':AM' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E0E0E0');
            $sheet->getStyle('AN' . $row . ':AP' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFE0B2');
            $sheet->getStyle('AQ' . $row . ':AU' . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFCCBC');
            $sheet->getRowDimension($row)->setRowHeight(30);

            $sheet->getStyle('AD3:AU' . $row)
                ->getNumberFormat()
                ->setFormatCode('_-$* #,##0.00_-;[Red]$* #,##0.00;_-$* "-"??_-;_-@_-');

            //TOTALES GLOBALES PORCENTAJE
            $sheet->setCellValue('AG' . $datosGlobalesTitulo, 'AHORRO PRECIO PUNTOS');
            $sheet->setCellValue('AH' . $datosGlobalesTitulo, 'AHORRO PRECIO CLIENTE');
            $sheet->setCellValue('AL' . $datosGlobalesTitulo, 'AHORRO FEE PUNTOS');
            $sheet->setCellValue('AM' . $datosGlobalesTitulo, 'AHORRO FEE CLIENTE');
            $sheet->setCellValue('AP' . $datosGlobalesTitulo, 'AHORRO ENVIO CLIENTE');
            $sheet->setCellValue('AT' . $datosGlobalesTitulo, 'AHORRO USUARIO');
            $sheet->setCellValue('AU' . $datosGlobalesTitulo, 'AHORRO CLIENTE');

            $sheet->setCellValue('AG' . $datosGlobalesPorcentaje, $data['totales']['ahorro_precio_puntos_global'] ?? '');
            $sheet->setCellValue('AH' . $datosGlobalesPorcentaje, $data['totales']['ahorro_precio_proveedor_global'] ?? '');
            $sheet->setCellValue('AL' . $datosGlobalesPorcentaje, $data['totales']['ahorro_fee_puntos_global'] ?? '');
            $sheet->setCellValue('AM' . $datosGlobalesPorcentaje, $data['totales']['ahorro_fee_proveedor_global'] ?? '');
            $sheet->setCellValue('AP' . $datosGlobalesPorcentaje, $data['totales']['ahorro_envio_proveedor_global'] ?? '');
            $sheet->setCellValue('AT' . $datosGlobalesPorcentaje, $data['totales']['ahorro_usuario_global'] ?? '');
            $sheet->setCellValue('AU' . $datosGlobalesPorcentaje, $data['totales']['ahorro_proveedor_global'] ?? '');

            $celdasAhorro = ['AG', 'AH', 'AL', 'AM', 'AP', 'AT', 'AU'];
            foreach ($celdasAhorro as $col) {
                $sheet->getStyle($col . $datosGlobalesTitulo . ':' . $col . $datosGlobalesPorcentaje)
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
            }

            $sheet->getStyle('AG' . $datosGlobalesTitulo . ':AH' . $datosGlobalesTitulo)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('C8E6C9');
            $sheet->getStyle('AL' . $datosGlobalesTitulo . ':AM' . $datosGlobalesTitulo)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E0E0E0');
            $sheet->getStyle('AP' . $datosGlobalesTitulo)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFE0B2');
            $sheet->getStyle('AT' . $datosGlobalesTitulo . ':AU' . $datosGlobalesTitulo)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFCCBC');


            $sheet->getStyle('AE' . $datosGlobalesTitulo . ':AU' . $datosGlobalesTitulo)->getFont()->setSize(14)->setBold(true);
            $sheet->getStyle('AE' . $datosGlobalesTitulo . ':AU' . $datosGlobalesTitulo)->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
            $sheet->getStyle('AE' . $datosGlobalesPorcentaje . ':AU' . $datosGlobalesPorcentaje)->getFont()->setSize(18)->setBold(true);
            $sheet->getStyle('AE' . $datosGlobalesPorcentaje . ':AU' . $datosGlobalesPorcentaje)->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                ->setWrapText(true);
            $sheet->getStyle('AE' . $datosGlobalesPorcentaje . ':AU' . $datosGlobalesPorcentaje)
                ->getNumberFormat()
                ->setFormatCode('0.00"%"');
            $sheet->getRowDimension($datosGlobalesTitulo)->setRowHeight(40);
            $sheet->getRowDimension($datosGlobalesPorcentaje)->setRowHeight(40);

            $totalColumnas = Coordinate::columnIndexFromString('AU');
            for ($i = 1; $i <= $totalColumnas; $i++) {
                $columnID = Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $fileName = 'reporte_canjes_' . now()->format('Ymd_His') . '.xlsx';

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0',
            ]);
        } catch (\Throwable $th) {
            return $this->sendError('Error al generar el Excel', $th->getMessage(), 500);
        }
    }
}
