<?php

namespace App\Http\Controllers\GroupAdmin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupJoinRequest;
use App\Services\Groups\JoinRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** QTV duyệt người xin vào nhóm qua link, hiện ở đầu trang Danh bạ (plan/13). */
class JoinRequestController extends Controller
{
    public function __construct(private readonly JoinRequests $joins) {}

    /** Người chờ duyệt kèm trạng thái link đăng ký, để trang Danh bạ hiện cả hai. */
    public function index(Group $group)
    {
        $requests = GroupJoinRequest::query()
            ->with('user')
            ->where('group_id', $group->id)
            ->where('status', 'pending')
            ->oldest()
            ->get()
            ->map(function (GroupJoinRequest $r) use ($group) {
                $match = $this->joins->matchingContact($group, $r->user);

                return [
                    'id' => $r->id,
                    'name' => $r->user->name,
                    'phone' => $r->user->phone,
                    'requested_at' => $r->created_at->toIso8601String(),
                    'contact' => $match ? ['id' => $match->id, 'full_name' => $match->full_name] : null,
                ];
            });

        return [
            'requests' => $requests,
            'link' => ['enabled' => $group->join_link_enabled, 'url' => url("/{$group->code}/register")],
        ];
    }

    public function approve(Request $request, Group $group, int $joinRequest)
    {
        $this->joins->approve($this->find($group, $joinRequest), $request->user());

        return response()->noContent();
    }

    /**
     * "Duyệt tất cả": nhận đúng các id QTV đang thấy trên màn hình, không duyệt
     * người vừa xin vào sau đó. Lỗi từng người không làm dừng cả loạt.
     */
    public function approveMany(Request $request, Group $group)
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer']])['ids'];
        $done = 0;
        $failed = [];

        $requests = GroupJoinRequest::query()->with(['group', 'user'])
            ->where('group_id', $group->id)->where('status', 'pending')->whereIn('id', $ids)->oldest()->get();

        foreach ($requests as $joinRequest) {
            try {
                $this->joins->approve($joinRequest, $request->user());
                $done++;
            } catch (ValidationException $e) {
                $failed[] = ['id' => $joinRequest->id, 'name' => $joinRequest->user->name, 'message' => collect($e->errors())->flatten()->first()];
            }
        }

        return ['approved' => $done, 'failed' => $failed];
    }

    public function reject(Request $request, Group $group, int $joinRequest)
    {
        $this->joins->reject($this->find($group, $joinRequest), $request->user());

        return response()->noContent();
    }

    private function find(Group $group, int $id): GroupJoinRequest
    {
        return GroupJoinRequest::query()->with(['group', 'user'])->where('group_id', $group->id)->findOrFail($id);
    }
}
