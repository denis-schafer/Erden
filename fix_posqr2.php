<?php
$file = 'C:\laragon\www\erden\resources\js\components\modules\pos\PosQR.vue';
$content = file_get_contents($file);

// Split content into lines
$lines = explode("\n", $content);
$output = [];
$keep = true;
$onUnmountedCount = 0;

for ($i = 0; $i < count($lines); $i++) {
    $line = $lines[$i];
    
    // Count onUnmounted occurrences
    if (strpos($line, 'onUnmounted(() =>') !== false) {
        $onUnmountedCount++;
        if ($onUnmountedCount > 1) {
            $keep = false; // Stop keeping lines after first onUnmounted
        }
    }
    
    if ($keep) {
        $output[] = $line;
    }
    
    // After first onUnmounted closes properly, check if we should start keeping again
    if ($keep === false && strpos($line, '});') !== false && $onUnmountedCount == 1) {
        // This is the end of first onUnmounted, but we already kept it
        // Now look for </script> to start keeping again
    }
    
    // If we find </script>, start keeping again (for the style section)
    if (strpos($line, '</script>') !== false) {
        $keep = true;
        $output[] = $line;
    }
}

// Now we need to also remove orphaned code between first onUnmounted and </script>
// Let's redo this more carefully
$lines = explode("\n", $content);
$output = [];
$inFirstOnUnmounted = false;
$braceLevel = 0;
$firstOnUnmountedEnded = false;
$seenScriptEnd = false;

for ($i = 0; $i < count($lines); $i++) {
    $line = $lines[$i];
    
    // Detect first onUnmounted
    if (!$firstOnUnmountedEnded && strpos($line, 'onUnmounted(() =>') !== false) {
        $inFirstOnUnmounted = true;
        $braceLevel = 0;
    }
    
    // Track braces in first onUnmounted
    if ($inFirstOnUnmounted && !$firstOnUnmountedEnded) {
        $openBraces = substr_count($line, '{');
        $closeBraces = substr_count($line, '}');
        $braceLevel += $openBraces - $closeBraces;
        
        $output[] = $line; // Keep this line
        
        if ($braceLevel <= 0 && strpos($line, '});') !== false) {
            $firstOnUnmountedEnded = true;
            $inFirstOnUnmounted = false;
        }
        continue;
    }
    
    // Skip everything until we hit </script>
    if (!$firstOnUnmountedEnded) {
        continue;
    }
    
    // After first onUnmounted ended, skip until </script>
    if (strpos($line, '</script>') !== false) {
        $seenScriptEnd = true;
        $output[] = $line;
        continue;
    }
    
    if ($seenScriptEnd) {
        $output[] = $line;
    }
}

$newContent = implode("\n", $output);
file_put_contents($file, $newContent);
echo "FIXED: Removed duplicate onUnmounted and orphaned code\n";
echo "Lines before: " . count($lines) . "\n";
echo "Lines after: " . count($output) . "\n";
