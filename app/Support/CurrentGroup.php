<?php

namespace App\Support;

use App\Models\Group;
use RuntimeException;

/**
 * Nhóm của request hiện tại, do middleware ResolveGroup đặt từ mã nhóm trên URL.
 * Global scope của BelongsToGroup đọc từ đây. Đăng ký singleton trong
 * AppServiceProvider nên mỗi request (và mỗi test) có một bản riêng.
 */
final class CurrentGroup
{
    private ?Group $group = null;

    public function set(?Group $group): void
    {
        $this->group = $group;
    }

    public function get(): ?Group
    {
        return $this->group;
    }

    public function id(): ?int
    {
        return $this->group?->id;
    }

    public function require(): Group
    {
        return $this->group ?? throw new RuntimeException('Chưa xác định nhóm hiện tại.');
    }
}
