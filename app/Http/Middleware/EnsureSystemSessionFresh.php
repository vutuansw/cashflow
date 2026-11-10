<?php

namespace App\Http\Middleware;

use App\Models\System\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phiên /system nhớ dấu mật khẩu lúc đăng nhập; quản trị viên đổi mật khẩu
 * thì các phiên khác lệch dấu và bị đăng xuất, xem plan/10. Không dựa vào cột
 * sessions.user_id được vì cột đó chỉ ghi người dùng guard web.
 */
class EnsureSystemSessionFresh
{
    private const KEY = 'system_password_hash';

    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('system')->user();

        if ($admin instanceof Admin) {
            $stored = $request->session()->get(self::KEY);
            if (! is_array($stored) || ($stored['id'] ?? null) !== $admin->getKey()) {
                // Phiên đăng nhập trước khi có tính năng này: ghi dấu, không đá ra.
                self::remember($request, $admin);
            } elseif (! hash_equals((string) ($stored['hash'] ?? ''), self::fingerprint($admin))) {
                Auth::guard('system')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return $next($request);
    }

    public static function remember(Request $request, Admin $admin): void
    {
        $request->session()->put(self::KEY, ['id' => $admin->getKey(), 'hash' => self::fingerprint($admin)]);
    }

    private static function fingerprint(Admin $admin): string
    {
        return hash_hmac('sha256', (string) $admin->getAuthPassword(), (string) config('app.key'));
    }
}
