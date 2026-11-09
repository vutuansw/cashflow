<?php

namespace App\Support;

use App\Models\BillScan;
use App\Models\Group;
use App\Models\TransactionPhoto;

/**
 * Đường dẫn ảnh đi qua route /media có kiểm quyền (routes/web.php), không bao
 * giờ trỏ thẳng vào file. Xem plan/03 mục 3.
 */
class MediaUrl
{
    public static function photo(Group $group, TransactionPhoto $photo, string $size = 'lg'): string
    {
        return "/media/{$group->code}/p/{$photo->id}/{$size}";
    }

    public static function bill(Group $group, BillScan $scan): string
    {
        return "/media/{$group->code}/b/{$scan->id}";
    }

    /** Ảnh đại diện/bìa: tên file ngẫu nhiên nằm trên URL nên đổi ảnh là đổi URL. */
    public static function brand(Group $group, string $kind): ?string
    {
        $path = $kind === 'avatar' ? $group->avatar_path : $group->cover_path;

        return $path === null ? null : "/media/{$group->code}/brand/{$kind}/".pathinfo($path, PATHINFO_FILENAME);
    }
}
