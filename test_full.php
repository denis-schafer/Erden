<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Generate the exact same way the controller does
$spreadsheet = new Spreadsheet();
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Resumen');
$sheet1->setCellValue('A1', 'Test');
$sheet1->getColumnDimension('A')->setAutoSize(true);
$sheet1->getColumnDimension('B')->setAutoSize(true);

$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Pedidos');
$sheet2->setCellValue('A1', 'ID');

$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle('Productos');
$sheet3->setCellValue('A1', 'Producto');

$sheet4 = $spreadsheet->createSheet();
$sheet4->setTitle('Gráficos');

// Add a simple chart
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

$sheet4->setCellValue('A1', 'Intervalo');
$sheet4->setCellValue('B1', 'Pedidos');
$sheet4->setCellValue('A2', '10:00')->setCellValue('B2', 5);
$sheet4->setCellValue('A3', '10:10')->setCellValue('B3', 3);

$label = new DataSeriesValues('String', 'Gráficos!$B$1', null, 1);
$cat = new DataSeriesValues('String', 'Gráficos!$A$2:$A$3', null, 2);
$val = new DataSeriesValues('Number', 'Gráficos!$B$2:$B$3', null, 2);
$series = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, [0], [$label], [$cat], [$val]);
$pa = new PlotArea(null, [$series]);
$le = new Legend();
$ti = new Title('Test');
$chart = new Chart('test', $ti, $le, $pa);
$chart->setTopLeftPosition('D1');
$chart->setBottomRightPosition('O16');
$sheet4->addChart($chart);

$sheet4->getColumnDimension('A')->setAutoSize(true);
$sheet4->getColumnDimension('B')->setAutoSize(true);

$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true);

$filename = 'estadisticas_test.xlsx';
$tempDir = sys_get_temp_dir();
$tempFile = $tempDir . DIRECTORY_SEPARATOR . $filename;
$writer->save($tempFile);

echo 'Size: ' . filesize($tempFile) . PHP_EOL;
echo 'First 4 bytes: ' . bin2hex(file_get_contents($tempFile, false, null, 0, 4)) . PHP_EOL;

$zip = new ZipArchive();
$res = $zip->open($tempFile);
if ($res === TRUE) {
    echo 'ZIP OK, files:' . PHP_EOL;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        echo '  ' . $zip->getNameIndex($i) . PHP_EOL;
    }
    $zip->close();
} else {
    echo 'ZIP FAIL: ' . $res . PHP_EOL;
}

// Copy to download location so user can test
copy($tempFile, 'C:\Users\Denis\Downloads\estadisticas_test.xlsx');
echo 'Copied to Downloads' . PHP_EOL;
