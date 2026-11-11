<?php

namespace App\Http\Presenters;

use App\Models\Contact;
use App\Models\Group;
use App\Services\Groups\GroupViewer;
use App\Services\Groups\MemberIndex;
use App\Support\MediaUrl;

/** Phần đầu trang nhóm: tên, ảnh, chế độ xem. Nhóm private vẫn lộ phần này. */
class GroupPresenter
{
    /** Số người hiện ở hàng avatar dưới tên nhóm (plan/08 mục 10d). */
    public const MEMBERS_PREVIEW = 20;

    public static function header(Group $group, GroupViewer $viewer): array
    {
        $data = [
            'code' => $group->code,
            'name' => $group->name,
            'visibility' => $group->visibility,
            'cover_url' => MediaUrl::groupCover($group),
            'can_view' => $viewer->canView(),
            'viewer' => $viewer->toArray(),
        ];

        if ($viewer->canView()) {
            $data['description'] = $group->description;
            $data['members_count'] = Contact::acrossGroups()->where('group_id', $group->id)->where('status', 'active')->count();
            $data['members_preview'] = self::membersPreview($group);
        }

        return $data;
    }

    /**
     * Hàng avatar thành viên như nhóm Facebook: người có ảnh đại diện trước, rồi người có tài khoản, rồi người
     * chỉ có trong danh bạ. Chỉ tên và ảnh (ai xem được timeline vốn đã thấy tên ở bảng đã đóng/chưa đóng).
     *
     * @return list<array{name: string, member_handle: ?string, avatar_url: ?string}>
     */
    private static function membersPreview(Group $group): array
    {
        $index = MemberIndex::of($group);

        return Contact::acrossGroups()
            ->where('group_id', $group->id)
            ->where('status', 'active')
            ->orderBy('sort_order')->orderBy('full_name')
            ->get(['id', 'full_name'])
            ->map(fn (Contact $c) => ['name' => $c->full_name] + ($index->contact($c->id) ?? ['member_handle' => null, 'avatar_url' => null]))
            ->sortBy(fn ($p) => $p['avatar_url'] !== null ? 0 : ($p['member_handle'] !== null ? 1 : 2))
            ->take(self::MEMBERS_PREVIEW)
            ->values()
            ->all();
    }
}
