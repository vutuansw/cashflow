<?php

namespace App\Http\Controllers\GroupAdmin;

use App\Http\Controllers\Controller;
use App\Http\Presenters\StatusPostPresenter;
use App\Models\Group;
use App\Models\StatusPost;
use App\Services\Groups\GroupViewer;
use Illuminate\Http\Request;

/**
 * Duyệt bài đăng thành viên khi nhóm bật "Chờ QTV duyệt". Bài bị từ chối
 * vẫn duyệt lại được. Không gửi email (plan/21 Q2). Xem plan/21 mục 7.
 */
class StatusPostReviewController extends Controller
{
    /** Hàng chờ (cũ trước, để duyệt theo thứ tự gửi) hoặc bài đã từ chối (mới trước). */
    public function index(Request $request, Group $group)
    {
        $status = $request->validate(['status' => ['nullable', 'in:pending,rejected']])['status'] ?? 'pending';
        $viewer = GroupViewer::of($request, $group);

        $items = StatusPostPresenter::withCardRelations(StatusPost::query(), $viewer)
            ->where('status', $status)
            ->when($status === 'pending', fn ($q) => $q->orderBy('id'), fn ($q) => $q->orderByDesc('reviewed_at')->orderByDesc('id'))
            ->limit(100)
            ->get();

        return [
            'data' => $items->map(fn (StatusPost $p) => StatusPostPresenter::card($p, $group, $viewer))->values(),
            'counts' => StatusPost::query()->whereIn('status', ['pending', 'rejected'])
                ->selectRaw('status, COUNT(*) as n')->groupBy('status')->toBase()->pluck('n', 'status'),
            'post_mode' => $group->post_mode,
        ];
    }

    public function approve(Request $request, Group $group, int $status)
    {
        $post = StatusPost::query()->whereIn('status', ['pending', 'rejected'])->findOrFail($status);
        // Duyệt không phải sửa bài: giữ updated_at để thẻ không hiện "đã sửa".
        $post->timestamps = false;
        $post->forceFill([
            'status' => 'published',
            'published_at' => now(),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'reject_reason' => null,
        ])->save();

        return ['pending' => self::pendingCount()];
    }

    public function reject(Request $request, Group $group, int $status)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);
        $post = StatusPost::query()->where('status', 'pending')->findOrFail($status);
        $post->timestamps = false;
        $post->forceFill([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'reject_reason' => trim((string) ($data['reason'] ?? '')) ?: null,
        ])->save();

        return ['pending' => self::pendingCount()];
    }

    public static function pendingCount(): int
    {
        return StatusPost::query()->where('status', 'pending')->count();
    }
}
