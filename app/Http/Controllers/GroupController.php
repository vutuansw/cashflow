<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\System\PlatformSetting;
use App\Rules\GroupCode;
use App\Services\Groups\GroupCreator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Kiểm tra mã nhóm khi đang gõ, và tạo thêm nhóm cho tài khoản đã đăng nhập. */
class GroupController extends Controller
{
    public function checkCode(Request $request)
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:150'],
        ]);

        $code = strtolower(trim((string) ($data['code'] ?? '')));
        $message = null;
        if ($code !== '') {
            (new GroupCode)->validate('code', $code, function ($m) use (&$message) {
                $message = $m;
            });
        }

        return [
            'code' => $code,
            'available' => $code !== '' && $message === null,
            'message' => $message,
            'suggestion' => filled($data['name'] ?? null) ? GroupCode::suggest($data['name']) : null,
        ];
    }

    public function store(Request $request, GroupCreator $creator)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', new GroupCode],
            'visibility' => ['required', 'in:public,private'],
            'opening_balance' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
        ]);

        $limit = (int) PlatformSetting::get('groups_per_user', config('cashflow.limits.groups_per_user'));
        if (Group::where('owner_id', $user->id)->count() >= $limit) {
            throw ValidationException::withMessages(['name' => "Mỗi tài khoản được tạo tối đa {$limit} nhóm."]);
        }

        $group = $creator->create($user, $data);

        return response()->json(['code' => $group->code, 'name' => $group->name], 201);
    }
}
