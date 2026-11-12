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
    {{-- Ảnh share luôn 1200×630; có sẵn kích thước thì Facebook/Messenger hiện ảnh ngay lần share đầu (plan/29). --}}
    @if ($meta['image'])
    <meta property="og:image" content="{{ $meta['image'] }}">
    <meta property="og:image:secure_url" content="{{ $meta['image'] }}">
    <meta property="og:image:type" content="{{ $meta['image_type'] }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $meta['image_alt'] }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $meta['title'] }}">
    <meta name="twitter:description" content="{{ $meta['description'] }}">
    <meta name="twitter:image" content="{{ $meta['image'] }}">
    @endif
    @if ($meta['demo_group'] ?? null)
    <meta name="wepo-demo-group" content="{{ $meta['demo_group'] }}">
    @endif
    <meta name="theme-color" content="#15803d">
    {{-- Mã bản build của chính trang này, so với /api/app để biết đã có bản deploy mới (plan/27). --}}
    @if ($build = \Illuminate\Support\Facades\Vite::manifestHash())
    <meta name="wepo-build" content="{{ $build }}">
    @endif
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/icons/favicon-32.png" type="image/png" sizes="32x32">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ config('cashflow.brand.name') }}">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-background font-sans text-foreground antialiased">
    <div id="app"></div>
</body>
</html>
