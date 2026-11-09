<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\User;
use App\Support\PhoneHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** Đăng nhập bằng SĐT + mật khẩu, đăng xuất, thông tin tài khoản. Xem plan/02 mục 1. */
class SessionController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function store(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $hash = PhoneHash::make($data['phone']);
        $key = 'login:'.($hash ?? 'invalid').'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'phone' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ])->status(429);
        }

        $user = $hash === null ? null : User::where('phone_hash', $hash)->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['phone' => __('auth.failed')]);
        }

        if ($user->status === 'locked') {
            throw ValidationException::withMessages(['phone' => 'Tài khoản đang bị khóa. Liên hệ trưởng nhóm.']);
        }

        RateLimiter::clear($key);
        Auth::guard('web')->login($user, remember: $data['remember'] ?? true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return new MeResource($user);
    }

    public function show(Request $request)
    {
        $user = $request->user();

        return $user === null ? response()->json(['data' => null]) : new MeResource($user);
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
