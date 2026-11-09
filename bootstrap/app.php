<?php

use App\Http\Middleware\EnsureGroupRole;
use App\Http\Middleware\EnsureGroupVisible;
use App\Http\Middleware\RequireFreshPassword;
use App\Http\Middleware\ResolveGroup;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // SPA cùng domain: API dùng session cookie + CSRF của nhóm middleware web, xem plan/02 mục 1.
            Route::middleware('web')->prefix('api')->group(base_path('routes/api.php'));
            // Webhook SePay không có session hay CSRF; tự kiểm tra API key của nhóm, xem plan/05 mục 3.
            Route::prefix('api/webhooks')->group(base_path('routes/webhooks.php'));
            // Khai báo sau cùng: route bắt mọi đường dẫn còn lại để trả khung SPA.
            Route::middleware('web')->group(base_path('routes/web.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'group' => ResolveGroup::class,
            'group.role' => EnsureGroupRole::class,
            'group.view' => EnsureGroupVisible::class,
            'password.fresh' => RequireFreshPassword::class,
        ]);

        // Nhóm hiện tại phải có trước khi Laravel nạp {category}, {contact}...
        // từ URL: global scope của BelongsToGroup cần nó để tìm đúng dòng.
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveGroup::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureGroupRole::class);

        $middleware->redirectGuestsTo('/dang-nhap');
        $middleware->redirectUsersTo('/nhom');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
