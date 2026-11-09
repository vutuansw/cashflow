<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="description" content="<?php echo e(config('cashflow.brand.description')); ?>">
    <meta name="theme-color" content="#15803d">
    <title><?php echo e(config('cashflow.brand.name')); ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="bg-background font-sans text-foreground antialiased">
    <div id="app"></div>
</body>
</html>
<?php /**PATH /Users/vutuansw/www/cashflow/resources/views/app.blade.php ENDPATH**/ ?>