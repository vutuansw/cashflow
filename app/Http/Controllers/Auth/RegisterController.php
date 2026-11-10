<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Services\Accounts\UserRegistrar;
use Illuminate\Http\Request;

/**
 * Đăng ký tài khoản, không kèm nhóm. Tạo nhóm là bước riêng qua
 * POST /api/groups, xem plan/02 mục 2 và plan/11. Đăng ký kèm xin vào nhóm
 * đi qua Group\JoinController (plan/13).
 */
class RegisterController extends Controller
{
    public function __invoke(Request $request, UserRegistrar $registrar)
    {
        $user = $registrar->register($request);

        return response()->json(['me' => new MeResource($user)], 201);
    }
}
