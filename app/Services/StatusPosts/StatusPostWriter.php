<?php

namespace App\Services\StatusPosts;

use App\Models\Group;
use App\Models\StatusPost;
use App\Models\StatusPostPhoto;
use App\Services\Groups\GroupViewer;
use App\Services\Media\ImageStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Tạo và sửa bài đăng thành viên. Ảnh xử lý như ảnh sự kiện của phiếu, lưu
 * trong groups/{id}/posts. Lưu database lỗi thì xóa ảnh vừa ghi. Xem plan/21.
 */
class StatusPostWriter
{
    public function __construct(private readonly ImageStore $images) {}

    /** @param  list<UploadedFile>  $photos */
    public function create(Group $group, GroupViewer $viewer, ?string $body, array $photos): StatusPost
    {
        $body = $this->clean($body);
        $this->ensureNotEmpty($body, count($photos));

        // Trưởng nhóm / QTV luôn hiện ngay; thành viên theo chế độ của nhóm (plan/21 mục 2).
        $pending = $viewer->postNeedsReview();
        $stored = $this->storePhotos($group, $photos);

        try {
            return DB::transaction(function () use ($viewer, $body, $pending, $stored) {
                $post = StatusPost::create([
                    'user_id' => $viewer->user->id,
                    'body' => $body,
                    'status' => $pending ? 'pending' : 'published',
                    'published_at' => $pending ? null : now(),
                ]);
                $this->attach($post, $stored, 0);

                return $post;
            });
        } catch (Throwable $e) {
            $this->forget($stored);
            throw $e;
        }
    }

    /**
     * Sửa chữ, thêm/bỏ ảnh. Bài đã duyệt sửa lại không phải duyệt lại (plan/21 Q1).
     *
     * @param  list<UploadedFile>  $photos  ảnh mới thêm
     * @param  list<int>  $removePhotoIds
     */
    public function update(StatusPost $post, ?string $body, array $photos, array $removePhotoIds): StatusPost
    {
        $body = $this->clean($body);
        $existing = $post->photos()->get();
        $removed = $existing->whereIn('id', $removePhotoIds);
        $kept = $existing->count() - $removed->count();

        $limit = config('cashflow.limits.photos_per_status');
        if ($kept + count($photos) > $limit) {
            throw ValidationException::withMessages(['photos' => "Mỗi bài tối đa {$limit} ảnh."]);
        }
        $this->ensureNotEmpty($body, $kept + count($photos));

        $stored = $this->storePhotos($post->group, $photos);

        try {
            DB::transaction(function () use ($post, $body, $removed, $stored, $existing) {
                $post->body = $body;
                // Đổi ảnh mà chữ giữ nguyên vẫn tính là đã sửa.
                $post->updated_at = now();
                $post->save();

                StatusPostPhoto::query()->whereIn('id', $removed->pluck('id'))->delete();
                $this->attach($post, $stored, ((int) $existing->max('sort_order')) + 1);
            });
        } catch (Throwable $e) {
            $this->forget($stored);
            throw $e;
        }

        $this->images->delete([...$removed->pluck('path'), ...$removed->pluck('thumb_path')]);

        return $post;
    }

    private function clean(?string $body): ?string
    {
        $body = trim(str_replace("\r\n", "\n", (string) $body));

        return $body === '' ? null : $body;
    }

    private function ensureNotEmpty(?string $body, int $photoCount): void
    {
        if ($body === null && $photoCount === 0) {
            throw ValidationException::withMessages(['body' => 'Bạn viết vài chữ hoặc chọn ít nhất một ảnh nhé.']);
        }
    }

    /** @return list<array{path: string, thumb_path: string, width: int, height: int}> */
    private function storePhotos(Group $group, array $photos): array
    {
        $stored = [];
        try {
            foreach ($photos as $file) {
                $stored[] = $this->images->storeEventPhoto($group, $file, 'posts');
            }
        } catch (Throwable $e) {
            $this->forget($stored);
            throw $e;
        }

        return $stored;
    }

    private function attach(StatusPost $post, array $stored, int $start): void
    {
        foreach ($stored as $i => $file) {
            $post->photos()->create($file + ['sort_order' => $start + $i]);
        }
    }

    private function forget(array $stored): void
    {
        $this->images->delete([...array_column($stored, 'path'), ...array_column($stored, 'thumb_path')]);
    }
}
