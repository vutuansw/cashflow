<?php

namespace App\Services\Groups;

use App\Models\Group;
use App\Models\GroupMember;
use App\Support\MediaUrl;

/**
 * Tra nhanh "tên này là thành viên nào" để dựng link trang thành viên
 * /{ma-nhom}/thanh-vien/{memberId} và ảnh đại diện. Chỉ tài khoản đang thuộc
 * nhóm; người trong danh bạ chưa có tài khoản thì không có trang. Xem plan/08
 * mục 1 và 6.
 *
 * Nạp một lần cho mỗi request (nhóm vài chục đến vài trăm người), dùng chung
 * cho thẻ phiếu, bình luận, bảng đã đóng/chưa đóng.
 */
class MemberIndex
{
    /** @var array<int, array{member_id: int, avatar_url: ?string}> */
    private array $byContact = [];

    /** @var array<int, array{member_id: int, avatar_url: ?string}> */
    private array $byUser = [];

    private function __construct(Group $group)
    {
        $members = GroupMember::query()
            ->where('group_id', $group->id)
            ->where('status', 'active')
            ->with('user:id,avatar_path')
            ->get(['id', 'user_id', 'contact_id']);

        foreach ($members as $m) {
            $entry = ['member_id' => $m->id, 'avatar_url' => $m->user ? MediaUrl::profile($m->user, 'avatar') : null];
            $this->byUser[$m->user_id] = $entry;
            if ($m->contact_id !== null) {
                $this->byContact[$m->contact_id] = $entry;
            }
        }
    }

    public static function of(Group $group): self
    {
        $key = "member_index.{$group->id}";
        $cached = request()->attributes->get($key);
        if ($cached instanceof self) {
            return $cached;
        }

        $index = new self($group);
        request()->attributes->set($key, $index);

        return $index;
    }

    /** @return array{member_id: int, avatar_url: ?string}|null */
    public function contact(?int $contactId): ?array
    {
        return $contactId === null ? null : ($this->byContact[$contactId] ?? null);
    }

    /** @return array{member_id: int, avatar_url: ?string}|null */
    public function user(?int $userId): ?array
    {
        return $userId === null ? null : ($this->byUser[$userId] ?? null);
    }
}
