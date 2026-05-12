<?php
$f = 'C:\Users\Denis\Downloads\estadisticas.xlsx';
echo 'File size: ' . filesize($f) . ' bytes' . PHP_EOL;
echo 'First 4 bytes: ' . bin2hex(file_get_contents($f, false, null, 0, 4)) . PHP_EOL;

$zip = new ZipArchive();
$res = $zip->open($f);
if ($res === TRUE) {
    echo 'ZIP opened successfully' . PHP_EOL;
    echo 'Num files: ' . $zip->numFiles . PHP_EOL;
    for ($i = 0; $i < min($zip->numFiles, 30); $i++) {
        $content = $zip->getFromIndex($i);
        echo '  ' . $zip->getNameIndex($i) . ' (' . strlen($content ?? '') . ' bytes)' . PHP_EOL;
    }
    $zip->close();
} else {
    echo 'ZIP open failed with code: ' . $res . PHP_EOL;
    // Code meanings: 1=error, 9=no such file, 11=open error, 19=not zip archive
    echo 'Possible issue: ';
    switch ($res) {
        case ZipArchive::ER_NOZIP:
            echo 'Not a ZIP archive (ER_NOZIP)';
            break;
        case ZipArchive::ER_OPEN:
            echo 'Can\'t open file (ER_OPEN)';
            break;
        case ZipArchive::ER_READ:
            echo 'Read error (ER_READ)';
            break;
        case ZipArchive::ER_SEEK:
            echo 'Seek error (ER_SEEK)';
            break;
        default:
            echo 'Unknown error code';
    }
    echo PHP_EOL;
}
