<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** Đăng nhập /system bằng email + mật khẩu, guard "system", xem plan/02 mục 6. */
class AuthController extends Controller
{
    /** Token CSRF của phiên /system cho SPA gửi trong header X-CSRF-TOKEN. */
    public function csrf(Request $request)
    {
        return ['token' => $request->session()->token()];
    }

    public function me()
    {
        $admin = Auth::guard('system')->user();

        return ['data' => $admin ? $this->present($admin) : null];
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = 'system-login:'.strtolower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Bạn thử sai quá nhiều lần, đợi '.RateLimiter::availableIn($key).' giây rồi thử lại.']);
        }

        if (! Auth::guard('system')->attempt(['email' => strtolower($data['email']), 'password' => $data['password'], 'status' => 'active'])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Email hoặc mật khẩu không đúng.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $admin = Auth::guard('system')->user();
        $admin->forceFill(['last_login_at' => now()])->save();

        return ['data' => $this->present($admin), 'csrf' => $request->session()->token()];
    }

    public function logout(Request $request)
    {
        Auth::guard('system')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    private function present(Admin $admin): array
    {
        return ['id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email];
    }
}
