<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Group;
use App\Models\Reaction;
use App\Models\Transaction;
use App\Services\Groups\GroupViewer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Tim và bình luận, chỉ tài khoản thuộc nhóm. QTV ẩn/xóa được mọi bình luận,
 * người viết sửa/xóa được bình luận của mình. Xem plan/03 mục 5.
 */
class InteractionController extends Controller
{
    public function heart(Request $request, Group $group, int $transaction)
    {
        $post = $this->visiblePost($request, $group, $transaction);
        $userId = $request->user()->id;

        $existing = Reaction::where('transaction_id', $post->id)->where('user_id', $userId)->first();
        if ($existing) {
            $existing->delete();
        } else {
            Reaction::firstOrCreate(['transaction_id' => $post->id, 'user_id' => $userId]);
        }

        return [
            'hearted' => $existing === null,
            'hearts_count' => Reaction::where('transaction_id', $post->id)->count(),
        ];
    }

    /** Ai xem được timeline thì xem được bình luận; bình luận bị ẩn chỉ QTV và người viết thấy. */
    public function comments(Request $request, Group $group, int $transaction)
    {
        $post = $this->visiblePost($request, $group, $transaction, mustInteract: false);
        $viewer = GroupViewer::of($request, $group);
        $userId = $request->user()?->id;

        return Comment::where('transaction_id', $post->id)
            ->with('user:id,name')
            ->when(! $viewer->canManage(), fn ($q) => $q->where(fn ($w) => $w->whereNull('hidden_at')->when($userId, fn ($o) => $o->orWhere('user_id', $userId))))
            ->orderBy('id')
            ->limit(500)
            ->get()
            ->map(fn (Comment $c) => $this->present($c, $viewer));
    }

    public function storeComment(Request $request, Group $group, int $transaction)
    {
        $post = $this->visiblePost($request, $group, $transaction);
        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $key = 'comment:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['body' => 'Bạn bình luận nhanh quá, thử lại sau ít phút.']);
        }
        RateLimiter::hit($key, 60);

        $comment = Comment::create([
            'transaction_id' => $post->id,
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
        ]);

        return response()->json($this->present($comment->load('user:id,name'), GroupViewer::of($request, $group)), 201);
    }

    public function updateComment(Request $request, Group $group, int $comment)
    {
        $item = $this->comment($group, $comment);
        abort_unless($item->user_id === $request->user()->id, 403, 'Chỉ người viết mới sửa được bình luận.');

        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);
        $item->update(['body' => trim($data['body'])]);

        return $this->present($item->load('user:id,name'), GroupViewer::of($request, $group));
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

        return $this->present($item->load('user:id,name'), GroupViewer::of($request, $group));
    }

    private function visiblePost(Request $request, Group $group, int $id, bool $mustInteract = true): Transaction
    {
        $viewer = GroupViewer::of($request, $group);
        if ($mustInteract && ! $viewer->canInteract()) {
            abort(403, 'Chỉ tài khoản của nhóm mới thả tim, bình luận được.');
        }

        return Transaction::query()
            ->when(! $viewer->canManage(), fn ($q) => $q->where('show_on_timeline', true))
            ->findOrFail($id);
    }

    /** Bình luận thuộc phiếu của nhóm hiện tại (phiếu có global scope nhóm). */
    private function comment(Group $group, int $id): Comment
    {
        return Comment::whereKey($id)->whereIn('transaction_id', Transaction::query()->select('id'))->firstOrFail();
    }

    private function present(Comment $c, GroupViewer $viewer): array
    {
        $mine = $viewer->user?->id === $c->user_id;

        return [
            'id' => $c->id,
            'body' => $c->body,
            'user' => $c->user?->name ?? 'Tài khoản đã xóa',
            'created_at' => $c->created_at->toIso8601String(),
            'edited' => $c->updated_at->gt($c->created_at->copy()->addSecond()),
            'hidden' => $c->hidden_at !== null,
            'mine' => $mine,
            'can_edit' => $mine,
            'can_delete' => $mine || $viewer->canManage(),
            'can_hide' => $viewer->canManage(),
        ];
    }
}
