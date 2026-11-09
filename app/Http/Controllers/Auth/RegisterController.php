<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\User;
use App\Rules\GroupCode;
use App\Services\Groups\GroupCreator;
use App\Support\PhoneHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/** Trưởng nhóm đăng ký tài khoản và lập nhóm trong một bước, xem plan/02 mục 2. */
class RegisterController extends Controller
{
    public function __invoke(Request $request, GroupCreator $creator)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
            'group_name' => ['required', 'string', 'max:150'],
            'group_code' => ['required', 'string', new GroupCode],
            'visibility' => ['required', 'in:public,private'],
            'opening_balance' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
            // Ô ẩn chống bot: người thật không nhìn thấy nên không điền.
            'website' => ['prohibited'],
        ]);

        $hash = PhoneHash::make($data['phone'])
            ?? throw ValidationException::withMessages(['phone' => 'Số điện thoại không hợp lệ.']);

        if (User::where('phone_hash', $hash)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'Số điện thoại này đã có tài khoản. Bạn đăng nhập rồi tạo thêm nhóm ở trang Chọn nhóm.',
            ]);
        }

        [$user, $group] = DB::connection('groups')->transaction(function () use ($data, $creator) {
            $user = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $group = $creator->create($user, [
                'name' => $data['group_name'],
                'code' => $data['group_code'],
                'visibility' => $data['visibility'],
                'opening_balance' => $data['opening_balance'] ?? 0,
            ]);

            return [$user, $group];
        });

        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'me' => new MeResource($user),
            'group' => ['code' => $group->code, 'name' => $group->name],
        ], 201);
    }
}
