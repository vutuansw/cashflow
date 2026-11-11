<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Http\Presenters\CommentPresenter;
use App\Models\Comment;
use App\Models\Group;
use App\Models\Reaction;
use App\Models\StatusPost;
use App\Models\Transaction;
use App\Services\Groups\GroupViewer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Tim và bình luận, chỉ tài khoản thuộc nhóm. QTV ẩn/xóa được mọi bình luận,
 * người viết sửa/xóa được bình luận của mình. Xem plan/03 mục 5.
 *
 * Dùng chung cho phiếu (/posts/{id}, cột transaction_id) và bài đăng thành
 * viên (/statuses/{id}, cột status_post_id); route truyền `target` để chọn.
 * Xem plan/21 mục 3, 5.
 */
class InteractionController extends Controller
{
    public function heart(Request $request, Group $group, int $post, string $target = 'transaction')
    {
        $column = $this->visiblePost($request, $group, $post, $target);
        $userId = $request->user()->id;

        $existing = Reaction::where($column, $post)->where('user_id', $userId)->first();
        if ($existing) {
            $existing->delete();
        } else {
            Reaction::firstOrCreate([$column => $post, 'user_id' => $userId]);
        }

        return [
            'hearted' => $existing === null,
            'hearts_count' => Reaction::where($column, $post)->count(),
        ];
    }

    /**
     * Ai xem được timeline thì xem được bình luận. Dưới phiếu đã có sẵn vài bình luận mới nhất;
     * "Xem thêm" gọi kèm `before` (id bình luận cũ nhất đang hiện) để lấy tiếp các bình luận cũ hơn.
     */
    public function comments(Request $request, Group $group, int $post, string $target = 'transaction')
    {
        $column = $this->visiblePost($request, $group, $post, $target, mustInteract: false);
        $viewer = GroupViewer::of($request, $group);
        $before = $request->validate(['before' => ['nullable', 'integer', 'min:1']])['before'] ?? null;

        $items = CommentPresenter::visibleTo(Comment::where($column, $post), $viewer)
            ->with('user:id,name')
            ->when($before, fn ($q) => $q->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit(CommentPresenter::PAGE + 1)
            ->get();

        return [
            'data' => $items->take(CommentPresenter::PAGE)->reverse()->values()->map(fn (Comment $c) => CommentPresenter::present($c, $viewer)),
            'has_more' => $items->count() > CommentPresenter::PAGE,
        ];
    }

    public function storeComment(Request $request, Group $group, int $post, string $target = 'transaction')
    {
        $column = $this->visiblePost($request, $group, $post, $target);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $key = 'comment:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['body' => 'Bạn bình luận nhanh quá, thử lại sau ít phút.']);
        }
        RateLimiter::hit($key, 60);

        $comment = Comment::create([
            $column => $post,
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
        ]);

        return response()->json(CommentPresenter::present($comment->load('user:id,name'), GroupViewer::of($request, $group)), 201);
    }

    public function updateComment(Request $request, Group $group, int $comment)
    {
        $item = $this->comment($group, $comment);
        abort_unless($item->user_id === $request->user()->id, 403, 'Chỉ người viết mới sửa được bình luận.');

        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);
        $item->update(['body' => trim($data['body'])]);

        return CommentPresenter::present($item->load('user:id,name'), GroupViewer::of($request, $group));
    }

    public function destroyComment(Request $request, Group $group, int $comment)
    {
        $item = $this->comment($group, $comment);
        $viewer = GroupViewer::of($request, $group);
        abort_unless($item->user_id === $request->user()->id || $viewer->canManage(), 403, 'Bạn không xóa được bình luận này.');

        $item->delete();

        return response()->noContent();
    }

    /** QTV ẩn hoặc hiện lại bình luận. */
    public function hideComment(Request $request, Group $group, int $comment)
    {
        abort_unless(GroupViewer::of($request, $group)->canManage(), 403, 'Chỉ quản trị viên ẩn được bình luận.');

        $item = $this->comment($group, $comment);
        $hide = $request->boolean('hidden', true);
        $item->update(['hidden_at' => $hide ? now() : null, 'hidden_by' => $hide ? $request->user()->id : null]);

        return CommentPresenter::present($item->load('user:id,name'), GroupViewer::of($request, $group));
    }

    /**
     * Kiểm bài tồn tại trong nhóm và người xem được thấy; trả tên cột khóa ở bảng reactions/comments.
     * Bài đăng chờ duyệt, bị từ chối hoặc bị ẩn thì không thả tim, bình luận được.
     */
    private function visiblePost(Request $request, Group $group, int $id, string $target, bool $mustInteract = true): string
    {
        $viewer = GroupViewer::of($request, $group);
        if ($mustInteract && ! $viewer->canInteract()) {
            abort(403, 'Chỉ tài khoản của nhóm mới thả tim, bình luận được.');
        }

        if ($target === 'status') {
            StatusPost::visibleTo(StatusPost::query(), $viewer)
                ->when($mustInteract, fn ($q) => $q->where('status', 'published')->whereNull('hidden_at'))
                ->findOrFail($id);

            return 'status_post_id';
        }

        Transaction::query()
            ->when(! $viewer->canManage(), fn ($q) => $q->where('show_on_timeline', true))
            ->findOrFail($id);

        return 'transaction_id';
    }

    /** Bình luận thuộc phiếu hoặc bài đăng của nhóm hiện tại (cả hai có global scope nhóm). */
    private function comment(Group $group, int $id): Comment
    {
        return Comment::whereKey($id)
            ->where(fn ($q) => $q
                ->whereIn('transaction_id', Transaction::query()->select('id'))
                ->orWhereIn('status_post_id', StatusPost::query()->select('id')))
            ->firstOrFail();
    }
}
