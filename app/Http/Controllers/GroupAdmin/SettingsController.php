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

    /** Ảnh đại diện hoặc ảnh bìa trên trang nhóm. */
    public function uploadImage(Request $request, Group $group, ImageStore $images)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:avatar,cover'],
            'image' => ['required', 'file', 'mimes:'.implode(',', config('cashflow.images.mimes')), 'max:'.config('cashflow.limits.photo_upload_max_kb')],
        ]);

        $column = $data['kind'].'_path';
        $old = $group->{$column};
        $group->update([$column => $images->storeGroupImage($group, $request->file('image'), $data['kind'])]);
        $images->delete([$old]);

        return $this->payload($request, $group);
    }

    public function deleteImage(Request $request, Group $group, string $kind, ImageStore $images)
    {
        $column = $kind.'_path';
        $images->delete([$group->{$column}]);
        $group->update([$column => null]);

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
            'avatar_url' => MediaUrl::brand($group, 'avatar'),
            'cover_url' => MediaUrl::brand($group, 'cover'),
            'visibility' => $group->visibility,
            'opening_balance' => $group->opening_balance,
            'opening_balance_date' => $group->opening_balance_date?->toDateString(),
            'bank_code' => $group->bank_code,
            'bank_account_no' => $group->bank_account_no,
            'bank_account_name' => $group->bank_account_name,
            'notify_emails' => $group->notify_emails ?? [],
            'my_role' => $member->role->value,
        ];
    }
}
