<?php

namespace App\Enums;

/** Vai trò của một tài khoản trong một nhóm, xem plan/02 mục 3. */
enum GroupRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Trưởng nhóm',
            self::Admin => 'Quản trị viên',
            self::Member => 'Thành viên',
        };
    }

    /** Trưởng nhóm và quản trị viên vào được trang quản trị nhóm. */
    public function canManage(): bool
    {
        return $this !== self::Member;
    }
}
