<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Models\BillScan;
use App\Models\Group;
use App\Models\Transaction;
use App\Models\TransactionPhoto;
use App\Services\Groups\GroupViewer;
use App\Services\Media\ImageStore;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phục vụ ảnh từ đĩa riêng tư sau khi kiểm quyền, xem plan/03 mục 3:
 * - ảnh sự kiện: ai xem được timeline nhóm thì xem được (phiếu ẩn khỏi timeline hoặc đã xóa: chỉ QTV);
 * - ảnh bill chứng từ: chỉ QTV;
 * - ảnh đại diện, ảnh bìa: công khai, kể cả nhóm private (màn hình mời đăng nhập vẫn hiện).
 */
class MediaController extends Controller
{
    public function photo(Request $request, Group $group, int $photo, string $size)
    {
        $viewer = GroupViewer::of($request, $group);
        abort_unless($viewer->canView(), 404);

        $item = TransactionPhoto::query()
            ->whereKey($photo)
            ->whereIn('transaction_id', Transaction::query()
                ->when($viewer->canManage(), fn ($q) => $q->withTrashed(), fn ($q) => $q->where('show_on_timeline', true))
                ->select('id'))
            ->firstOrFail();

        return $this->file($size === 'sm' ? $item->thumb_path : $item->path, $group->isPublic());
    }

    public function bill(Request $request, Group $group, int $scan)
    {
        abort_unless(GroupViewer::of($request, $group)->canManage(), 404);

        $item = BillScan::query()->whereKey($scan)->whereNotNull('path')->firstOrFail();

        return $this->file($item->path, false);
    }

    public function brand(Group $group, string $kind, string $name)
    {
        $path = $kind === 'avatar' ? $group->avatar_path : $group->cover_path;
        abort_if($path === null || pathinfo($path, PATHINFO_FILENAME) !== $name, 404);

        return $this->file($path, true);
    }

    private function file(?string $path, bool $public): Response
    {
        $disk = ImageStore::disk();
        abort_if($path === null || ! $disk->exists($path), 404);

        // Tên file ngẫu nhiên và không bao giờ ghi đè nên cache lâu được; nhóm private thì không cho proxy giữ.
        $response = new BinaryFileResponse($disk->path($path), 200, [
            'Content-Type' => 'image/webp',
            'X-Robots-Tag' => 'noindex',
            'X-Content-Type-Options' => 'nosniff',
        ], $public);

        return $public
            ? $response->setPublic()->setMaxAge(31536000)->setImmutable()
            : $response->setPrivate()->setMaxAge(86400);
    }
}
