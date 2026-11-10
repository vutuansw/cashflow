<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo e($meta['title']); ?></title>
    <meta name="description" content="<?php echo e($meta['description']); ?>">
    <?php if($meta['noindex']): ?>
    <meta name="robots" content="noindex">
    <?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo e(config('cashflow.brand.name')); ?>">
    <meta property="og:title" content="<?php echo e($meta['title']); ?>">
    <meta property="og:description" content="<?php echo e($meta['description']); ?>">
    <meta property="og:url" content="<?php echo e(url()->current()); ?>">
    <?php if($meta['image']): ?>
    <meta property="og:image" content="<?php echo e($meta['image']); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <?php endif; ?>
    <meta name="theme-color" content="#15803d">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Quỹ nhóm">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="bg-background font-sans text-foreground antialiased">
    <div id="app"></div>
</body>
</html>
<?php /**PATH /Users/vutuansw/www/cashflow/resources/views/app.blade.php ENDPATH**/ ?>