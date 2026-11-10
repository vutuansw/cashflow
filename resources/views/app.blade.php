<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $meta['title'] }}</title>
    <meta name="description" content="{{ $meta['description'] }}">
    @if ($meta['noindex'])
    <meta name="robots" content="noindex">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('cashflow.brand.name') }}">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:description" content="{{ $meta['description'] }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($meta['image'])
    <meta property="og:image" content="{{ $meta['image'] }}">
    <meta name="twitter:card" content="summary_large_image">
    @endif
    <meta name="theme-color" content="#15803d">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Quỹ nhóm">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background font-sans text-foreground antialiased">
    <div id="app"></div>
</body>
</html>
