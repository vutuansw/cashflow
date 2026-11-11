<?php

use App\Http\Middleware\EnsureGroupRole;
use App\Http\Middleware\EnsureGroupVisible;
use App\Http\Middleware\EnsureSystemSessionFresh;
use App\Http\Middleware\RequireFreshPassword;
use App\Http\Middleware\ResolveGroup;
use App\Http\Middleware\SystemCsrf;
use App\Http\Middleware\UseSystemSession;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // SPA cùng domain: API dùng session cookie + CSRF của nhóm middleware web, xem plan/02 mục 1.
            Route::middleware('web')->prefix('api')->group(base_path('routes/api.php'));
            // Quản trị nền tảng /system: phiên đăng nhập riêng, xem plan/02 mục 6.
            Route::middleware('system')->prefix('api/system')->group(base_path('routes/system.php'));
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

        // Nhóm middleware cho /api/system: như "web" nhưng cookie session riêng và không đặt cookie XSRF-TOKEN.
        $middleware->group('system', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            UseSystemSession::class,
            StartSession::class,
            SystemCsrf::class,
            EnsureSystemSessionFresh::class,
            SubstituteBindings::class,
        ]);
        $middleware->prependToPriorityList(StartSession::class, UseSystemSession::class);

        $middleware->redirectGuestsTo('/dang-nhap');
        $middleware->redirectUsersTo('/nhom');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Bị giới hạn số lần (throttle): báo tiếng Việt kèm thời gian chờ thay cho "Too Many Attempts.", xem plan/18.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }
            $seconds = (int) ($e->getHeaders()['Retry-After'] ?? 60);
            $wait = $seconds >= 60 ? (int) ceil($seconds / 60).' phút' : $seconds.' giây';

            return response()->json(['message' => "Bạn thử quá nhiều lần. Đợi {$wait} rồi thử lại nhé."], 429, $e->getHeaders());
        });
    })->create();
