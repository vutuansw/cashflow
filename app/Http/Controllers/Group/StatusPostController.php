<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Http\Presenters\StatusPostPresenter;
use App\Models\Group;
use App\Models\StatusPost;
use App\Services\Groups\GroupViewer;
use App\Services\StatusPosts\StatusPostWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Bài đăng thành viên (chữ + ảnh, không qua phiếu thu chi). Thành viên đăng
 * theo chế độ của nhóm (đăng luôn / chờ duyệt / tắt); người đăng sửa, xóa bài
 * của mình; QTV ẩn, xóa mọi bài. Xem plan/21.
 */
class StatusPostController extends Controller
{
    public function __construct(private readonly StatusPostWriter $writer) {}

    /** Một bài, mở từ link chia sẻ. */
    public function show(Request $request, Group $group, int $status)
    {
        $viewer = GroupViewer::of($request, $group);
        $post = StatusPostPresenter::withCardRelations(StatusPost::visibleTo(StatusPost::query(), $viewer), $viewer)->findOrFail($status);

        return StatusPostPresenter::card($post, $group, $viewer);
    }

    public function store(Request $request, Group $group)
    {
        $viewer = GroupViewer::of($request, $group);
        abort_unless($viewer->canPost(), 403, 'Nhóm đang tắt đăng bài, chỉ Ban quản trị đăng được.');

        $data = $this->validated($request);

        $key = 'status-post:'.$request->user()->id;
        $perHour = config('cashflow.limits.statuses_per_hour');
        if (RateLimiter::tooManyAttempts($key, $perHour)) {
            throw ValidationException::withMessages(['body' => 'Bạn đăng nhiều bài quá, thử lại sau ít phút nhé.']);
        }

        $post = $this->writer->create($group, $viewer, $data['body'] ?? null, $request->file('photos', []));
        RateLimiter::hit($key, 3600);

        return response()->json($this->card($post, $group, $viewer), 201);
    }

    /** POST thay vì PUT: PHP không đọc multipart của PUT. */
    public function update(Request $request, Group $group, int $status)
    {
        $viewer = GroupViewer::of($request, $group);
        $post = StatusPost::query()->findOrFail($status);
        abort_unless($post->user_id === $request->user()->id, 403, 'Chỉ người đăng mới sửa được bài.');
        abort_if($post->status === 'rejected', 403, 'Bài không được duyệt thì chỉ xóa được, không sửa được.');

        $data = $this->validated($request) + $request->validate([
            'remove_photo_ids' => ['nullable', 'array'],
            'remove_photo_ids.*' => ['integer'],
        ]);

        $this->writer->update($post, $data['body'] ?? null, $request->file('photos', []), array_map('intval', $data['remove_photo_ids'] ?? []));

        return $this->card($post, $group, $viewer);
    }

    public function destroy(Request $request, Group $group, int $status)
    {
        $viewer = GroupViewer::of($request, $group);
        $post = StatusPost::query()->findOrFail($status);
        abort_unless($post->user_id === $request->user()->id || $viewer->canManage(), 403, 'Bạn không xóa được bài này.');

        $post->delete();

        return response()->noContent();
    }

    /** QTV ẩn hoặc hiện lại bài đã đăng; người đăng vẫn thấy bài kèm nhãn "đã bị ẩn". */
    public function hide(Request $request, Group $group, int $status)
    {
        $viewer = GroupViewer::of($request, $group);
        abort_unless($viewer->canManage(), 403, 'Chỉ quản trị viên ẩn được bài.');

        $post = StatusPost::query()->where('status', 'published')->findOrFail($status);
        $hide = $request->boolean('hidden', true);
        // Ẩn không phải sửa bài: giữ updated_at để thẻ không hiện "đã sửa".
        $post->timestamps = false;
        $post->forceFill(['hidden_at' => $hide ? now() : null, 'hidden_by' => $hide ? $request->user()->id : null])->save();

        return $this->card($post, $group, $viewer);
    }

    private function validated(Request $request): array
    {
        $limit = config('cashflow.limits.photos_per_status');
        $mimes = implode(',', config('cashflow.images.mimes'));
        $maxKb = config('cashflow.limits.photo_upload_max_kb');

        return $request->validate([
            'body' => ['nullable', 'string', 'max:'.StatusPost::MAX_BODY],
            'photos' => ['nullable', 'array', "max:{$limit}"],
            'photos.*' => ['file', "mimes:{$mimes}", "max:{$maxKb}"],
        ], [
            'photos.max' => "Mỗi bài tối đa {$limit} ảnh.",
        ], [
            'body' => 'nội dung',
            'photos' => 'ảnh',
            'photos.*' => 'ảnh',
        ]);
    }

    private function card(StatusPost $post, Group $group, GroupViewer $viewer): array
    {
        $fresh = StatusPostPresenter::withCardRelations(StatusPost::query(), $viewer)->findOrFail($post->id);

        return StatusPostPresenter::card($fresh, $group, $viewer);
    }
}
