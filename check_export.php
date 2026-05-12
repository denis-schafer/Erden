<?php
$f = 'C:\laragon\www\erden\storage\logs\export_last.xlsx';
if (!file_exists($f)) { echo "File not found\n"; exit; }
$c = file_get_contents($f);
echo "Size: " . strlen($c) . "\n";
echo "First 4 hex: " . bin2hex(substr($c, 0, 4)) . "\n";
echo "Has BOM: " . (substr($c, 0, 3) === "\xEF\xBB\xBF" ? "YES" : "NO") . "\n";
echo "Is ZIP: " . (substr($c, 0, 2) === 'PK' ? "YES" : "NO") . "\n";

// If it's valid ZIP, check contents
if (substr($c, 0, 2) === 'PK') {
    $tmp = sys_get_temp_dir() . '/export_check.xlsx';
    file_put_contents($tmp, $c);
    $z = new ZipArchive();
    if ($z->open($tmp) === TRUE) {
        echo "ZIP valid, files:\n";
        for ($i = 0; $i < $z->numFiles; $i++) {
            echo "  " . $z->getNameIndex($i) . "\n";
        }
        $z->close();
    } else {
        echo "ZIP invalid\n";
    }
    unlink($tmp);
}
