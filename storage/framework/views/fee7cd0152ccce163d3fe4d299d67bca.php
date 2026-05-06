<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erden - Sistema de Gestión</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php
    $protocol = 'http';
    if (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
    ) {
        $protocol = 'https';
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $assetBase = $protocol . '://' . $host . '/build/';
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $jsFile = $manifest['resources/js/app.js']['file'] ?? 'assets/app-DTS85hLW.js';
    ?>
    <?php $__currentLoopData = $manifest['resources/js/app.js']['css'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $css): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <link rel="stylesheet" href="<?php echo e($assetBase); ?><?php echo e($css); ?>">
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <script type="module" src="<?php echo e($assetBase); ?><?php echo e($jsFile); ?>"></script>
</head>
<body>
    <div id="app"></div>
</body>
</html><?php /**PATH C:\laragon\www\erden\resources\views/spa.blade.php ENDPATH**/ ?>