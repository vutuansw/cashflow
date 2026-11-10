<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Đổi mật khẩu, quên mật khẩu qua email, đặt lại mật khẩu. Xem plan/02 mục 1. Sửa thông tin cá nhân ở ProfileController. */
class PasswordController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            // Đang dùng mật khẩu tạm thì không hỏi mật khẩu cũ: QTV vừa đưa cho họ.
            'current_password' => [$user->must_change_password ? 'nullable' : 'required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        if (! $user->must_change_password && ! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => __('validation.current_password')]);
        }

        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Mật khẩu mới phải khác mật khẩu đang dùng.']);
        }

        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();

        return new MeResource($user);
    }

    /**
     * Tài khoản có email thì gửi link đặt lại; không có thì hướng dẫn hỏi
     * trưởng nhóm. Câu trả lời giống nhau để không lộ SĐT nào có tài khoản.
     */
    public function forgot(Request $request)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']]);

        $user = User::wherePhone($data['phone'])->first();
        if ($user?->email) {
            PasswordBroker::broker('users')->sendResetLink(['email' => $user->email]);
        }

        return response()->json([
            'message' => 'Nếu tài khoản có email, link đặt lại mật khẩu đã được gửi tới email đó. '
                .'Nếu không có email, bạn nhờ trưởng nhóm hoặc quản trị viên đặt lại mật khẩu giúp.',
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        $status = PasswordBroker::broker('users')->reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'must_change_password' => false])->save();
        });

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => __($status)]);
    }
}
