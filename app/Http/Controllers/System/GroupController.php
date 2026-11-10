<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Mail\GroupStatusMail;
use App\Models\BillScan;
use App\Models\Contact;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\System\GroupService;
use App\Models\Transaction;
use App\Models\User;
use App\Rules\GroupCode;
use App\Services\Groups\GroupCreator;
use App\Services\GroupServiceStatus;
use App\Services\Media\ImageStore;
use App\Services\System\AuditLogger;
use App\Services\Transactions\GroupStats;
use App\Support\CurrentGroup;
use App\Support\PhoneHash;
use App\Support\PhoneNumber;
use App\Support\TempPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Quản lý nhóm ở /system: duyệt, khóa/mở khóa, sửa, xóa, tạo hộ, xem số
 * liệu. Không xem được SĐT/địa chỉ danh bạ của nhóm, chỉ SĐT trưởng nhóm để
 * liên hệ. Xem plan/02 mục 6.
 */
class GroupController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly GroupServiceStatus $status,
    ) {}

    public function index(Request $request)
    {
        $f = $request->validate([
            'review' => ['nullable', 'in:pending_review,approved,rejected'],
            'service' => ['nullable', 'in:active,locked'],
            'deleted' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Group::query()->with('owner:id,name')
            ->when($request->boolean('deleted'), fn ($q) => $q->onlyTrashed());

        // Lọc theo trạng thái ở cashflow_system: lấy id nhóm trước (khác database nên không join được).
        if (! empty($f['review']) || ! empty($f['service'])) {
            $ids = GroupService::query()
                ->when($f['review'] ?? null, fn ($q, $v) => $q->where('review_status', $v))
                ->when($f['service'] ?? null, fn ($q, $v) => $q->where('service_status', $v))
                ->pluck('group_id');
            $query->whereIn('id', $ids);
        }

        if ($q = trim($f['q'] ?? '')) {
            $phone = PhoneNumber::normalize($q);
            $query->where(function ($w) use ($q, $phone) {
                $w->where('code', 'like', '%'.addcslashes(strtolower($q), '%_\\').'%')
                    ->orWhere('name', 'like', '%'.addcslashes($q, '%_\\').'%');
                // Tìm theo SĐT trưởng nhóm qua hash, SĐT không lưu bản rõ.
                if ($phone !== null) {
                    $w->orWhereIn('owner_id', User::query()->where('phone_hash', PhoneHash::ofNormalized($phone))->select('id'));
                }
            });
        }

        $page = $query->orderByDesc('id')->paginate(30);
        $services = GroupService::query()->whereIn('group_id', collect($page->items())->pluck('id'))->get()->keyBy('group_id');

        return [
            'data' => collect($page->items())->map(fn (Group $g) => $this->summary($g, $services->get($g->id))),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ];
    }

    public function show(int $group)
    {
        $model = Group::withTrashed()->with('owner')->findOrFail($group);
        $service = $this->service($model);
        $count = fn (string $class, array $where = []) => $class::acrossGroups()->where('group_id', $model->id)->when($where, fn ($q) => $q->where($where))->count();

        $ai = BillScan::acrossGroups()->where('group_id', $model->id)->whereNotNull('model')
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as scans, SUM(cost_usd) as cost")
            ->groupBy('month')->orderByDesc('month')->toBase()->get()
            ->map(fn ($r) => ['month' => $r->month, 'scans' => (int) $r->scans, 'cost_usd' => round((float) $r->cost, 4)]);

        app(CurrentGroup::class)->set($model);

        return $this->summary($model, $service) + [
            'description' => $model->description,
            'owner' => $model->owner ? [
                'id' => $model->owner->id,
                'name' => $model->owner->name,
                'phone' => $model->owner->phone,
                'email' => $model->owner->email,
            ] : null,
            'counts' => [
                'contacts' => $count(Contact::class, [['status', '=', 'active']]),
                'accounts' => GroupMember::query()->where('group_id', $model->id)->where('status', 'active')->count(),
                'transactions' => $count(Transaction::class),
            ],
            'balance' => $model->trashed() ? null : app(GroupStats::class)->balance($model),
            'storage_mb' => $this->storageMb($model),
            'ai_usage' => $ai,
            'ai_monthly_quota' => $service->ai_monthly_quota,
            'note' => $service->note,
            'lock_reason' => $service->lock_reason,
            'reviewed_at' => $service->reviewed_at?->toIso8601String(),
        ];
    }

    /** Tạo nhóm hộ khách: tài khoản trưởng nhóm (mới thì có mật khẩu tạm) + nhóm, duyệt luôn. */
    public function store(Request $request, GroupCreator $creator)
    {
        $data = $request->validate([
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_phone' => ['required', 'string', 'max:20'],
            'owner_email' => ['nullable', 'email', 'max:190'],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', new GroupCode],
            'visibility' => ['required', 'in:public,private'],
            'opening_balance' => ['nullable', 'integer', 'min:0'],
        ], [], ['owner_name' => 'tên trưởng nhóm', 'owner_phone' => 'SĐT trưởng nhóm', 'name' => 'tên nhóm', 'code' => 'mã nhóm']);

        $phone = PhoneNumber::normalize($data['owner_phone']);
        if ($phone === null) {
            throw ValidationException::withMessages(['owner_phone' => 'Số điện thoại không hợp lệ.']);
        }

        $tempPassword = null;
        $owner = User::query()->wherePhone($phone)->first();
        if ($owner === null) {
            if (! empty($data['owner_email']) && User::query()->where('email', strtolower($data['owner_email']))->exists()) {
                throw ValidationException::withMessages(['owner_email' => 'Email này đã có tài khoản khác dùng.']);
            }
            $tempPassword = TempPassword::make();
            $owner = User::create([
                'name' => $data['owner_name'],
                'phone' => $phone,
                'email' => isset($data['owner_email']) ? strtolower($data['owner_email']) : null,
                'password' => $tempPassword,
                'must_change_password' => true,
            ]);
        }

        $group = $creator->create($owner, [
            'name' => $data['name'],
            'code' => strtolower($data['code']),
            'visibility' => $data['visibility'],
            'opening_balance' => $data['opening_balance'] ?? 0,
        ]);
        GroupService::query()->where('group_id', $group->id)->update(['review_status' => 'approved', 'reviewed_by' => $request->user('system')->id, 'reviewed_at' => now()]);
        $this->audit->log('create_group', $group->id, ['code' => $group->code, 'owner_user_id' => $owner->id, 'new_owner' => $tempPassword !== null]);

        return response()->json([
            'group' => $this->summary($group, $this->service($group)),
            'owner_existing' => $tempPassword === null,
            'temp_password' => $tempPassword,
            'login_url' => url('/dang-nhap'),
        ], 201);
    }

    public function update(Request $request, int $group)
    {
        $model = Group::query()->findOrFail($group);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', new GroupCode($model->id)],
            'visibility' => ['required', 'in:public,private'],
            'ai_monthly_quota' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $before = ['name' => $model->name, 'code' => $model->code, 'visibility' => $model->visibility];
        $model->forceFill(['name' => $data['name'], 'code' => strtolower($data['code']), 'visibility' => $data['visibility']])->save();
        $service = $this->service($model);
        $service->forceFill(['ai_monthly_quota' => $data['ai_monthly_quota'] ?? null, 'note' => $data['note'] ?? null])->save();

        $this->audit->log($before['code'] !== $model->code ? 'change_code' : 'update_group', $model->id, [
            'before' => $before + ['ai_monthly_quota' => $service->getOriginal('ai_monthly_quota')],
            'after' => $data,
        ]);

        return $this->show($model->id);
    }

    public function review(Request $request, int $group)
    {
        $model = Group::query()->findOrFail($group);
        $data = $request->validate(['status' => ['required', 'in:approved,rejected,pending_review'], 'note' => ['nullable', 'string', 'max:2000']]);

        $service = $this->service($model);
        $service->forceFill([
            'review_status' => $data['status'],
            'reviewed_by' => $request->user('system')->id,
            'reviewed_at' => now(),
            'note' => $data['note'] ?? $service->note,
        ])->save();
        $this->audit->log($data['status'] === 'approved' ? 'approve' : ($data['status'] === 'rejected' ? 'reject' : 'unreview'), $model->id, $data);

        return $this->show($model->id);
    }

    /** Khóa/mở khóa: nhóm khóa thì mọi trang và webhook trả "Nhóm đang tạm khóa". Báo email cho nhóm. */
    public function lock(Request $request, int $group)
    {
        $model = Group::query()->findOrFail($group);
        $data = $request->validate([
            'locked' => ['required', 'boolean'],
            'reason' => [Rule::requiredIf($request->boolean('locked')), 'nullable', 'string', 'max:255'],
            'notify' => ['nullable', 'boolean'],
        ], ['reason.required' => 'Ghi lý do khóa nhóm.']);

        $service = $this->service($model);
        $service->forceFill([
            'service_status' => $data['locked'] ? 'locked' : 'active',
            'lock_reason' => $data['locked'] ? $data['reason'] : null,
        ])->save();
        $this->status->forget($model->id);
        $this->audit->log($data['locked'] ? 'lock' : 'unlock', $model->id, ['reason' => $data['reason'] ?? null]);

        if ($request->boolean('notify', true)) {
            $to = array_values(array_filter([...($model->notify_emails ?? []), $model->owner?->email]));
            if ($to !== []) {
                Mail::to(array_unique($to))->queue(new GroupStatusMail($model, (bool) $data['locked'], $data['reason'] ?? null));
            }
        }

        return $this->show($model->id);
    }

    /** Xóa mềm, gõ lại mã nhóm để xác nhận. Dữ liệu xóa hẳn sau 30 ngày (cashflow:purge). */
    public function destroy(Request $request, int $group)
    {
        $model = Group::query()->findOrFail($group);
        $request->validate(['confirm_code' => ['required', 'string']]);
        if (strtolower($request->string('confirm_code')) !== $model->code) {
            throw ValidationException::withMessages(['confirm_code' => 'Gõ đúng mã nhóm để xác nhận xóa.']);
        }

        $model->delete();
        $this->status->forget($model->id);
        $this->audit->log('delete_group', $model->id, ['code' => $model->code, 'name' => $model->name]);

        return response()->noContent();
    }

    public function restore(int $group)
    {
        $model = Group::onlyTrashed()->findOrFail($group);
        if (Group::query()->where('code', $model->code)->exists()) {
            throw ValidationException::withMessages(['group' => 'Mã nhóm đã có nhóm khác dùng, đổi mã trước khi khôi phục.']);
        }
        $model->restore();
        $this->audit->log('restore_group', $model->id, ['code' => $model->code]);

        return $this->show($model->id);
    }

    private function service(Group $group): GroupService
    {
        // Nhóm tạo lúc cashflow_system lỗi có thể thiếu dòng này (GroupCreator): tạo bù.
        return GroupService::query()->firstOrCreate(['group_id' => $group->id], ['review_status' => 'pending_review']);
    }

    private function summary(Group $g, ?GroupService $s): array
    {
        return [
            'id' => $g->id,
            'code' => $g->code,
            'name' => $g->name,
            'visibility' => $g->visibility,
            'owner_name' => $g->owner?->name,
            'review_status' => $s?->review_status ?? 'pending_review',
            'service_status' => $s?->service_status ?? 'active',
            'created_at' => $g->created_at?->toIso8601String(),
            'deleted_at' => $g->deleted_at?->toIso8601String(),
        ];
    }

    private function storageMb(Group $group): float
    {
        $disk = ImageStore::disk();
        $bytes = 0;
        foreach ($disk->allFiles("groups/{$group->id}") as $file) {
            $bytes += $disk->size($file);
        }

        return round($bytes / 1048576, 1);
    }
}
