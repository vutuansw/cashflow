<?php

namespace App\Services\Accounts;

use App\Models\User;
use App\Support\PhoneHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Tạo tài khoản WePo và đăng nhập luôn. Dùng chung cho /register (plan/11)
 * và link đăng ký vào nhóm /{ma-nhom}/register (plan/13).
 */
class UserRegistrar
{
    public function register(Request $request): User
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
            // Ô ẩn chống bot: người thật không nhìn thấy nên không điền.
            'website' => ['prohibited'],
        ]);

        $hash = PhoneHash::make($data['phone'])
            ?? throw ValidationException::withMessages(['phone' => 'Số điện thoại không hợp lệ.']);

        if (User::where('phone_hash', $hash)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'Số điện thoại này đã có tài khoản.',
            ]);
        }

        $user = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
        ]);

        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return $user;
    }
}
