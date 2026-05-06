<?php
$file = 'C:\laragon\www\erden\resources\js\components\modules\pos\PosQR.vue';
$content = file_get_contents($file);

// Find the position after the FIRST onUnmounted closing (around line 636)
// The correct onUnmounted ends with clearing successTimeout (line 633-635)
$pattern = '/onUnmounted\(\(\) => \{.*?if \(successTimeout\.value\) \{\s*clearTimeout\(successTimeout\.value\);\s*\}\s*\}\);/s';
if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
    $pos = $matches[0][1] + strlen($matches[0][0]);
    
    // Find </script>
    $scriptEnd = strpos($content, '</script>', $pos);
    if ($scriptEnd !== false) {
        $before = substr($content, 0, $pos);
        $after = substr($content, $scriptEnd);
        $newContent = $before . "\n" . $after;
        file_put_contents($file, $newContent);
        echo "FIXED: Removed duplicate onUnmounted blocks\n";
    } else {
        echo "ERROR: </script> not found\n";
    }
} else {
    echo "ERROR: Pattern not found\n";
}
