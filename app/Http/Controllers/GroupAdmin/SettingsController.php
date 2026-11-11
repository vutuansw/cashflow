<?php

namespace App\Http\Controllers\GroupAdmin;

use App\Enums\GroupRole;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use App\Services\GroupServiceStatus;
use App\Services\Media\ImageStore;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Cài đặt nhóm, chuyển trưởng nhóm, xóa nhóm. Xem plan/02 mục 3, plan/06 mục 3. */
class SettingsController extends Controller
{
    public function show(Request $request, Group $group)
    {
        return $this->payload($request, $group);
    }

    public function update(Request $request, Group $group)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'rules' => ['nullable', 'string', 'max:3000'],
            'visibility' => ['required', 'in:public,private'],
            'opening_balance' => ['required', 'integer', 'min:0', 'max:1000000000000'],
            'opening_balance_date' => ['nullable', 'date'],
            'bank_code' => ['nullable', 'string', 'max:30'],
            'bank_account_no' => ['nullable', 'string', 'regex:/^[0-9A-Za-z]{4,30}$/'],
            'bank_account_name' => ['nullable', 'string', 'max:120'],
            'notify_emails' => ['nullable', 'array', 'max:5'],
            'notify_emails.*' => ['email', 'max:190'],
        ]);

        $data['bank_account_name'] = isset($data['bank_account_name']) ? mb_strtoupper($data['bank_account_name']) : null;
        $group->update($data);

        return $this->payload($request, $group);
    }

    /** Ảnh bìa đầu trang nhóm. Nhóm không có ảnh đại diện (plan/08 mục 10d). */
    public function uploadImage(Request $request, Group $group, ImageStore $images)
    {
        $request->validate([
            'kind' => ['nullable', 'in:cover'],
            'image' => ['required', 'file', 'mimes:'.implode(',', config('cashflow.images.mimes')), 'max:'.config('cashflow.limits.photo_upload_max_kb')],
        ]);

        $old = $group->cover_path;
        $group->update(['cover_path' => $images->storeGroupCover($group, $request->file('image'))]);
        $images->delete([$old]);

        return $this->payload($request, $group);
    }

    public function deleteImage(Request $request, Group $group, ImageStore $images)
    {
        $images->delete([$group->cover_path]);
        $group->update(['cover_path' => null]);

        return $this->payload($request, $group);
    }

    /**
     * Bật/tắt nhận biến động số dư qua SePay. Bật thì sinh API key ngẫu nhiên
     * 40 ký tự để dán vào cấu hình webhook bên my.sepay.vn (plan/05 mục 2).
     */
    public function sepay(Request $request, Group $group)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'rotate' => ['nullable', 'boolean']]);

        if (! $data['enabled']) {
            $group->forceFill(['sepay_api_key' => null])->save();
        } elseif ($group->sepay_api_key === null || ($data['rotate'] ?? false)) {
            $group->forceFill(['sepay_api_key' => Str::random(40)])->save();
        }

        return $this->payload($request, $group);
    }

    /** Bật/tắt link đăng ký vào nhóm /{ma-nhom}/register (plan/13). */
    public function joinLink(Request $request, Group $group)
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $group->update(['join_link_enabled' => $data['enabled']]);

        return $this->payload($request, $group);
    }

    /** Bật/tắt từng loại email thông báo. */
    public function notifications(Request $request, Group $group)
    {
        $data = $request->validate(['sepay' => ['required', 'boolean']]);
        $group->update(['notify_settings' => array_merge($group->notify_settings ?? [], $data)]);

        return $this->payload($request, $group);
    }

    public function transferOwner(Request $request, Group $group)
    {
        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $me = $request->attributes->get('group_member');

        $target = GroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', $data['user_id'])
            ->where('status', 'active')
            ->first();

        if ($target === null || $target->id === $me->id) {
            throw ValidationException::withMessages(['user_id' => 'Chọn một thành viên khác đang có tài khoản trong nhóm.']);
        }

        DB::connection('groups')->transaction(function () use ($group, $me, $target) {
            $target->update(['role' => GroupRole::Owner]);
            $me->update(['role' => GroupRole::Admin]);
            $group->update(['owner_id' => $target->user_id]);
        });

        return $this->payload($request, $group->refresh());
    }

    public function destroy(Request $request, Group $group, GroupServiceStatus $status)
    {
        $request->validate(['confirm_code' => ['required', 'string']]);

        if ($request->string('confirm_code')->lower()->toString() !== $group->code) {
            throw ValidationException::withMessages(['confirm_code' => 'Gõ đúng mã nhóm để xác nhận xóa.']);
        }

        $group->delete();
        $status->forget($group->id);

        return response()->noContent();
    }

    private function payload(Request $request, Group $group): array
    {
        $member = $request->attributes->get('group_member');

        return [
            'code' => $group->code,
            'name' => $group->name,
            'description' => $group->description,
            'rules' => $group->rules,
            'cover_url' => MediaUrl::groupCover($group),
            'visibility' => $group->visibility,
            'opening_balance' => $group->opening_balance,
            'opening_balance_date' => $group->opening_balance_date?->toDateString(),
            'bank_code' => $group->bank_code,
            'bank_account_no' => $group->bank_account_no,
            'bank_account_name' => $group->bank_account_name,
            'notify_emails' => $group->notify_emails ?? [],
            'notify_settings' => ['sepay' => ($group->notify_settings['sepay'] ?? true) !== false],
            'sepay' => [
                'enabled' => $group->sepay_api_key !== null,
                'api_key' => $group->sepay_api_key,
                'webhook_url' => url("/api/webhooks/sepay/{$group->code}"),
                'last_hook_at' => $group->sepay_last_hook_at?->toIso8601String(),
            ],
            'join_link' => [
                'enabled' => $group->join_link_enabled,
                'url' => url("/{$group->code}/register"),
            ],
            'my_role' => $member->role->value,
        ];
    }
}
