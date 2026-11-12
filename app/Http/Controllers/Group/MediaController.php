<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Models\BillScan;
use App\Models\Group;
use App\Models\StatusPost;
use App\Models\StatusPostPhoto;
use App\Models\Transaction;
use App\Models\TransactionPhoto;
use App\Models\User;
use App\Services\Groups\GroupViewer;
use App\Services\Media\ImageStore;
use App\Services\Media\ShareImage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phục vụ ảnh từ đĩa riêng tư sau khi kiểm quyền, xem plan/03 mục 3:
 * - ảnh sự kiện: ai xem được timeline nhóm thì xem được (phiếu ẩn khỏi timeline hoặc đã xóa: chỉ QTV);
 * - ảnh bài đăng thành viên: như ảnh sự kiện, bài chờ duyệt/bị ẩn chỉ người đăng và QTV (plan/21);
 * - ảnh bill chứng từ: chỉ QTV;
 * - ảnh bìa nhóm: công khai, kể cả nhóm private (màn hình mời đăng nhập vẫn hiện);
 * - ảnh đại diện, ảnh bìa trang cá nhân: công khai qua tên file ngẫu nhiên (plan/08 mục 4);
 * - ảnh share (og:image) JPEG 1200×630 của ảnh bìa nhóm và ảnh cá nhân: công khai như ảnh gốc (plan/29).
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

    /**
     * Ảnh bài đăng thành viên (plan/21 mục 4): như ảnh phiếu, nhưng bài chờ duyệt, bị từ chối hoặc bị ẩn
     * chỉ người đăng và QTV xem được, và khi đó không cho proxy giữ.
     */
    public function statusPhoto(Request $request, Group $group, int $photo, string $size)
    {
        $viewer = GroupViewer::of($request, $group);
        abort_unless($viewer->canView(), 404);

        $item = StatusPostPhoto::query()
            ->whereKey($photo)
            ->whereIn('status_post_id', StatusPost::visibleTo(StatusPost::query(), $viewer)->select('id'))
            ->with('statusPost')
            ->firstOrFail();
        $post = $item->statusPost;

        return $this->file($size === 'sm' ? $item->thumb_path : $item->path, $group->isPublic() && $post->isPublished() && $post->hidden_at === null);
    }

    public function bill(Request $request, Group $group, int $scan)
    {
        abort_unless(GroupViewer::of($request, $group)->canManage(), 404);

        $item = BillScan::query()->whereKey($scan)->whereNotNull('path')->firstOrFail();

        return $this->file($item->path, false);
    }

    public function groupCover(Group $group, string $name)
    {
        $path = $group->cover_path;
        abort_if($path === null || pathinfo($path, PATHINFO_FILENAME) !== $name, 404);

        return $this->file($path, true);
    }

    /** Ảnh share (og:image) của ảnh bìa nhóm: công khai như ảnh bìa, kể cả nhóm private (plan/29). */
    public function groupShare(Group $group, string $name, ShareImage $share)
    {
        return $this->share($group->cover_path, $name, $share);
    }

    /** Ảnh share của ảnh đại diện/bìa cá nhân (plan/29). */
    public function profileShare(int $user, string $kind, string $name, ShareImage $share)
    {
        return $this->share(User::query()->whereKey($user)->value($kind.'_path'), $name, $share);
    }

    /** Chỉ trả ảnh share của đúng file gốc đang dùng; tạo ở lần xin đầu tiên. */
    private function share(?string $source, string $name, ShareImage $share): Response
    {
        abort_if($source === null || pathinfo($source, PATHINFO_FILENAME) !== $name, 404);

        return $this->file($share->ensure($source), true, 'image/jpeg');
    }

    /** Ảnh đại diện/bìa trang cá nhân: công khai, chỉ trả đúng file đang dùng. Xem plan/08 mục 4. */
    public function profile(int $user, string $kind, string $name)
    {
        $path = User::query()->whereKey($user)->value($kind.'_path');
        abort_if($path === null || pathinfo($path, PATHINFO_FILENAME) !== $name, 404);

        return $this->file($path, true);
    }

    private function file(?string $path, bool $public, string $type = 'image/webp'): Response
    {
        $disk = ImageStore::disk();
        abort_if($path === null || ! $disk->exists($path), 404);

        // Tên file ngẫu nhiên và không bao giờ ghi đè nên cache lâu được; nhóm private thì không cho proxy giữ.
        $response = new BinaryFileResponse($disk->path($path), 200, [
            'Content-Type' => $type,
            'X-Robots-Tag' => 'noindex',
            'X-Content-Type-Options' => 'nosniff',
        ], $public);

        return $public
            ? $response->setPublic()->setMaxAge(31536000)->setImmutable()
            : $response->setPrivate()->setMaxAge(86400);
    }
}
