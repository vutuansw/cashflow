<?php

namespace App\Http\Controllers\GroupAdmin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Group;
use App\Models\Transaction;
use App\Services\Transactions\TransactionWriter;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** QTV ẩn / bỏ ẩn phiếu thu khỏi box "Top đóng góp" ngay trên trang nhóm. Xem plan/22 mục 3. */
class TopContributorController extends Controller
{
    /** Phiếu thu của chiến dịch: của một người (để chọn phiếu cần ẩn), hoặc các phiếu đã ẩn (để bỏ ẩn). */
    public function transactions(Request $request, Group $group, Campaign $campaign)
    {
        $data = $request->validate([
            'contact_id' => ['required_without:hidden', 'nullable', 'integer'],
            'hidden' => ['nullable', 'boolean'],
        ]);

        return Transaction::query()
            ->where('campaign_id', $campaign->id)
            ->where('type', 'income')
            ->when($request->boolean('hidden'), fn ($q) => $q->where('exclude_from_top', true), fn ($q) => $q->where('contact_id', $data['contact_id']))
            ->with('contact:id,group_id,full_name')
            ->orderBy('occurred_at')->orderBy('id')
            ->get()
            ->map(fn (Transaction $t) => [
                'id' => $t->id,
                'amount' => $t->amount,
                'occurred_at' => $t->occurred_at->toIso8601String(),
                'title' => $t->title,
                'person' => $t->personName(),
                'exclude_from_top' => $t->exclude_from_top,
            ]);
    }

    public function update(Request $request, Group $group, Transaction $transaction, TransactionWriter $writer)
    {
        $data = $request->validate(['exclude' => ['required', 'boolean']]);
        if ($transaction->type->value !== 'income' || $transaction->campaign_id === null) {
            throw ValidationException::withMessages(['exclude' => 'Chỉ phiếu thu gắn chiến dịch mới tính vào Top đóng góp.']);
        }

        $writer->setExcludeFromTop($transaction, $request->user(), (bool) $data['exclude']);

        return ['id' => $transaction->id, 'exclude_from_top' => $transaction->exclude_from_top];
    }
}
