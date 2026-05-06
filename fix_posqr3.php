<?php
$file = 'C:\laragon\www\erden\resources\js\components\modules\pos\PosQR.vue';
$content = file_get_contents($file);
$lines = explode("\n", $content);
$output = [];
$inFirstOnUnmounted = false;
$braceLevel = 0;
$firstOnUnmountedEnded = false;

for ($i = 0; $i < count($lines); $i++) {
    $line = $lines[$i];
    
    // Detect start of first onUnmounted
    if (!$firstOnUnmountedEnded && strpos($line, 'onUnmounted(() =>') !== false) {
        $inFirstOnUnmounted = true;
        $braceLevel = 0;
    }
    
    // If we're inside the first onUnmounted, track braces
    if ($inFirstOnUnmounted && !$firstOnUnmountedEnded) {
        $openBraces = substr_count($line, '{') - substr_count($line, '${');
        $closeBraces = substr_count($line, '}') - substr_count($line, '${');
        $braceLevel += $openBraces - $closeBraces;
        
        $output[] = $line;
        
        // Check if this line contains the closing of onUnmounted
        if ($braceLevel <= 0 && strpos($line, '});') !== false) {
            $firstOnUnmountedEnded = true;
            $inFirstOnUnmounted = false;
        }
        continue;
    }
    
    // Skip everything after first onUnmounted until we hit </script>
    if (!$firstOnUnmountedEnded) {
        continue;
    }
    
    // After first onUnmounted ended, keep everything including </script> and styles
    $output[] = $line;
}

$newContent = implode("\n", $output);
file_put_contents($file, $newContent);
echo "FIXED!\n";
echo "Original lines: " . count($lines) . "\n";
echo "Fixed lines: " . count($output) . "\n";
