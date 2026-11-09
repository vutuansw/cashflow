<?php

namespace App\Http\Presenters;

use App\Models\Contact;
use App\Models\Group;
use App\Services\Groups\GroupViewer;
use App\Support\MediaUrl;

/** Phần đầu trang nhóm: tên, ảnh, chế độ xem. Nhóm private vẫn lộ phần này. */
class GroupPresenter
{
    public static function header(Group $group, GroupViewer $viewer): array
    {
        $data = [
            'code' => $group->code,
            'name' => $group->name,
            'visibility' => $group->visibility,
            'avatar_url' => MediaUrl::brand($group, 'avatar'),
            'cover_url' => MediaUrl::brand($group, 'cover'),
            'can_view' => $viewer->canView(),
            'viewer' => $viewer->toArray(),
        ];

        if ($viewer->canView()) {
            $data['description'] = $group->description;
            $data['members_count'] = Contact::acrossGroups()->where('group_id', $group->id)->where('status', 'active')->count();
        }

        return $data;
    }
}
