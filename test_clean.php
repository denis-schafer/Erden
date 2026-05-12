<?php
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$ss = new Spreadsheet();
$ss->getActiveSheet()->setCellValue('A1', 'Test');
$writer = new Xlsx($ss);
$writer->setIncludeCharts(false);
$tmp = sys_get_temp_dir() . '/clean_test.xlsx';
$writer->save($tmp);

echo 'Size: ' . filesize($tmp) . PHP_EOL;
echo 'First 4: ' . bin2hex(file_get_contents($tmp, false, null, 0, 4)) . PHP_EOL;

$z = new ZipArchive();
if ($z->open($tmp) === TRUE) {
    echo 'Valid ZIP' . PHP_EOL;
    $z->close();
} else {
    echo 'Not valid ZIP' . PHP_EOL;
}

// Now test with a chart
$ss2 = new Spreadsheet();
$s2 = $ss2->getActiveSheet();
$s2->setTitle('Graficos');
$s2->setCellValue('A1', 'X')->setCellValue('B1', 'Y');
$s2->setCellValue('A2', 1)->setCellValue('B2', 5);
$s2->setCellValue('A3', 2)->setCellValue('B3', 3);

use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

$label = new DataSeriesValues('String', 'Graficos!$B$1', null, 1);
$cat = new DataSeriesValues('String', 'Graficos!$A$2:$A$3', null, 2);
$val = new DataSeriesValues('Number', 'Graficos!$B$2:$B$3', null, 2);
$series = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, [0], [$label], [$cat], [$val]);
$pa = new PlotArea(null, [$series]);
$le = new Legend();
$ti = new Title('Test');
$chart = new Chart('test', $ti, $le, $pa);
$chart->setTopLeftPosition('D1');
$chart->setBottomRightPosition('O16');
$s2->addChart($chart);

$writer2 = new Xlsx($ss2);
$writer2->setIncludeCharts(true);
$tmp2 = sys_get_temp_dir() . '/chart_test.xlsx';
$writer2->save($tmp2);

echo 'With chart size: ' . filesize($tmp2) . PHP_EOL;
echo 'With chart first 4: ' . bin2hex(file_get_contents($tmp2, false, null, 0, 4)) . PHP_EOL;
$z2 = new ZipArchive();
if ($z2->open($tmp2) === TRUE) {
    echo 'With chart: Valid ZIP' . PHP_EOL;
    for ($i = 0; $i < $z2->numFiles; $i++) {
        echo '  ' . $z2->getNameIndex($i) . PHP_EOL;
    }
    $z2->close();
} else {
    echo 'With chart: Not valid ZIP' . PHP_EOL;
}

unlink($tmp);
unlink($tmp2);
