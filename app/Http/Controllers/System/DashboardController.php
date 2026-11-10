<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\BillScan;
use App\Models\Group;
use App\Models\System\Admin;
use App\Models\System\AdminAuditLog;
use App\Models\System\GroupService;
use App\Models\Transaction;
use App\Models\User;
use App\Services\System\AuditLogger;
use App\Support\TempPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Tổng quan, chi phí AI, nhật ký, quản trị viên của /system. Xem plan/02 mục 6. */
class DashboardController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function overview()
    {
        $month = now()->startOfMonth();
        $ai = BillScan::acrossGroups()->whereNotNull('model')->where('created_at', '>=', $month)
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(cost_usd), 0) as cost')->toBase()->first();

        return [
            'groups' => [
                'total' => Group::query()->count(),
                'new_7_days' => Group::query()->where('created_at', '>=', now()->subDays(7))->count(),
                'pending_review' => GroupService::query()->where('review_status', 'pending_review')
                    ->whereIn('group_id', Group::query()->pluck('id'))->count(),
                'locked' => GroupService::query()->where('service_status', 'locked')->count(),
            ],
            'users' => User::query()->count(),
            'transactions' => Transaction::acrossGroups()->count(),
            'ai_this_month' => ['scans' => (int) $ai->n, 'cost_usd' => round((float) $ai->cost, 4), 'cost_vnd' => (int) round($ai->cost * config('ai.usd_vnd'))],
        ];
    }

    /** Lượt đọc bill và chi phí theo nhóm trong một tháng (mặc định tháng này). */
    public function aiCosts(Request $request)
    {
        $month = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfDay();

        $rows = BillScan::acrossGroups()->whereNotNull('model')
            ->whereBetween('created_at', [$start, $start->copy()->endOfMonth()])
            ->selectRaw('group_id, COUNT(*) as scans, SUM(fallback_used) as fallbacks, SUM(status = \'failed\') as failed, SUM(prompt_tokens + completion_tokens) as tokens, SUM(cost_usd) as cost')
            ->groupBy('group_id')->orderByDesc('cost')->toBase()->get();
        $groups = Group::withTrashed()->whereIn('id', $rows->pluck('group_id'))->get(['id', 'code', 'name'])->keyBy('id');
        $rate = config('ai.usd_vnd');

        return [
            'month' => $month,
            'usd_vnd' => $rate,
            'total' => ['scans' => (int) $rows->sum('scans'), 'cost_usd' => round((float) $rows->sum('cost'), 4), 'cost_vnd' => (int) round($rows->sum('cost') * $rate)],
            'rows' => $rows->map(fn ($r) => [
                'group_id' => (int) $r->group_id,
                'code' => $groups[$r->group_id]->code ?? '?',
                'name' => $groups[$r->group_id]->name ?? '(đã xóa)',
                'scans' => (int) $r->scans,
                'fallbacks' => (int) $r->fallbacks,
                'failed' => (int) $r->failed,
                'tokens' => (int) $r->tokens,
                'cost_usd' => round((float) $r->cost, 4),
                'cost_vnd' => (int) round($r->cost * $rate),
            ])->values(),
        ];
    }

    public function auditLogs(Request $request)
    {
        $f = $request->validate(['group_id' => ['nullable', 'integer'], 'page' => ['nullable', 'integer', 'min:1']]);
        $page = AdminAuditLog::query()
            ->when($f['group_id'] ?? null, fn ($q, $v) => $q->where('group_id', $v))
            ->orderByDesc('id')->paginate(50);
        $admins = Admin::query()->pluck('name', 'id');
        $groups = Group::withTrashed()->whereIn('id', collect($page->items())->pluck('group_id')->filter())->pluck('code', 'id');

        return [
            'data' => collect($page->items())->map(fn (AdminAuditLog $l) => [
                'id' => $l->id,
                'action' => $l->action,
                'admin' => $admins[$l->admin_id] ?? null,
                'group_id' => $l->group_id,
                'group_code' => $groups[$l->group_id] ?? null,
                'payload' => $l->payload,
                'ip' => $l->ip,
                'created_at' => $l->created_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ];
    }

    public function admins()
    {
        return Admin::query()->orderBy('id')->get(['id', 'name', 'email', 'status', 'last_login_at']);
    }

    /** Thêm quản trị viên /system, trả mật khẩu tạm hiện một lần. */
    public function storeAdmin(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('system.admins', 'email')],
        ]);
        $password = TempPassword::make().TempPassword::make();
        $admin = Admin::query()->create(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $password, 'status' => 'active']);
        $this->audit->log('create_admin', null, ['admin_id' => $admin->id, 'email' => $admin->email]);

        return response()->json(['admin' => $admin->only(['id', 'name', 'email', 'status']), 'temp_password' => $password], 201);
    }

    public function updateAdmin(Request $request, int $admin)
    {
        $model = Admin::query()->findOrFail($admin);
        $data = $request->validate(['status' => ['required', 'in:active,locked']]);
        if ($model->id === $request->user('system')->id && $data['status'] === 'locked') {
            abort(422, 'Không tự khóa tài khoản của mình.');
        }
        $model->forceFill(['status' => $data['status']])->save();
        if ($data['status'] === 'locked') {
            // Đăng xuất mọi phiên của người bị khóa: xóa remember token.
            $model->forceFill(['remember_token' => null])->save();
        }
        $this->audit->log($data['status'] === 'locked' ? 'lock_admin' : 'unlock_admin', null, ['admin_id' => $model->id]);

        return $model->only(['id', 'name', 'email', 'status', 'last_login_at']);
    }
}
