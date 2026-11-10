<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\Group;
use App\Services\Accounts\UserRegistrar;
use App\Services\Groups\JoinRequests;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Trang /{ma-nhom}/register: tạo tài khoản kèm xin vào nhóm, hoặc tài khoản sẵn có xin vào (plan/13). */
class JoinController extends Controller
{
    public function __construct(private readonly JoinRequests $joins) {}

    public function show(Request $request, Group $group)
    {
        return [
            'code' => $group->code,
            'name' => $group->name,
            'cover_url' => MediaUrl::groupCover($group),
            'enabled' => $group->join_link_enabled,
            // null khi chưa đăng nhập; member | pending | none
            'status' => $this->joins->statusOf($group, $request->user('web')),
        ];
    }

    /** Người mới: tạo tài khoản, đăng nhập luôn và gửi yêu cầu vào nhóm. */
    public function register(Request $request, Group $group, UserRegistrar $registrar)
    {
        if (! $group->join_link_enabled) {
            abort(403, 'Nhóm này không nhận đăng ký qua link.');
        }

        $user = DB::connection('groups')->transaction(function () use ($request, $group, $registrar) {
            $user = $registrar->register($request);
            $this->joins->request($group, $user);

            return $user;
        });

        return response()->json(['me' => new MeResource($user), 'status' => 'pending'], 201);
    }

    /** Người đã có tài khoản và đang đăng nhập: xin vào nhóm. */
    public function store(Request $request, Group $group)
    {
        $this->joins->request($group, $request->user());

        return response()->json(['status' => 'pending'], 201);
    }
}
