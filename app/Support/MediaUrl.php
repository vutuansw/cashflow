<?php

namespace App\Support;

use App\Models\BillScan;
use App\Models\Group;
use App\Models\TransactionPhoto;
use App\Models\User;

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

    /**
     * Ảnh bìa nhóm (nhóm không có ảnh đại diện, plan/08 mục 10d): tên file ngẫu nhiên nằm trên URL nên đổi ảnh là
     * đổi URL. Giữ đường dẫn /brand/cover để link cũ vẫn chạy.
     */
    public static function groupCover(Group $group): ?string
    {
        $path = $group->cover_path;

        return $path === null ? null : "/media/{$group->code}/brand/cover/".pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Ảnh đại diện/bìa trang cá nhân, công khai như ảnh nhóm. Mã nhóm dài ít
     * nhất 3 ký tự nên tiền tố /media/u không trùng nhóm nào. Xem plan/08 mục 4.
     */
    public static function profile(User $user, string $kind): ?string
    {
        $path = $kind === 'avatar' ? $user->avatar_path : $user->cover_path;

        return $path === null ? null : "/media/u/{$user->id}/{$kind}/".pathinfo($path, PATHINFO_FILENAME);
    }
}
