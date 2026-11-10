<?php

namespace App\Http\Controllers\GroupAdmin;

use App\Http\Controllers\Controller;
use App\Http\Presenters\TransactionPresenter;
use App\Http\Requests\TransactionRequest;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Group;
use App\Models\Transaction;
use App\Services\Transactions\TransactionWriter;
use App\Support\VietnameseText;
use Illuminate\Http\Request;

/** Phiếu thu chi trong quản trị nhóm, xem plan/03 mục 2. */
class TransactionController extends Controller
{
    public function __construct(private readonly TransactionWriter $writer) {}

    /**
     * Danh sách có lọc; kèm tổng thu, tổng chi của bộ lọc. Tổng không tính bộ lọc thu/chi: màn danh sách bấm ô Thu
     * hoặc Chi để lọc, hai ô vẫn phải giữ số của kỳ chứ không về 0.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'type' => ['nullable', 'in:income,expense'],
            'category_id' => ['nullable', 'integer'],
            'campaign_id' => ['nullable', 'integer'],
            'source' => ['nullable', 'in:manual,ai_bill,sepay'],
            'q' => ['nullable', 'string', 'max:100'],
            'trashed' => ['nullable', 'boolean'],
        ]);

        $query = Transaction::query()
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed()->where('deleted_at', '>=', now()->subDays(config('cashflow.restore_days'))))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('occurred_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('occurred_at', '<', now()->parse($v)->addDay()->startOfDay()))
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($filters['campaign_id'] ?? null, fn ($q, $v) => $q->where('campaign_id', $v))
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('title', 'like', '%'.addcslashes($v, '%_\\').'%')
                ->orWhere('payer_name', 'like', '%'.addcslashes($v, '%_\\').'%')
                ->orWhereIn('contact_id', Contact::where('search_name', 'like', '%'.addcslashes(VietnameseText::fold($v), '%_\\').'%')->select('id'))));

        $totals = (clone $query)->selectRaw('type, SUM(amount) as total, COUNT(*) as n')->groupBy('type')->toBase()->get()->keyBy('type');
        $query->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v));

        $page = $query
            ->with(['category', 'contact:id,group_id,full_name', 'campaign:id,name'])
            ->withCount(['photos', 'billScans' => fn ($q) => $q->whereNotNull('path')])
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate(30);

        return [
            'data' => collect($page->items())->map(fn (Transaction $t) => TransactionPresenter::row($t)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'totals' => [
                'income' => (int) ($totals['income']->total ?? 0),
                'expense' => (int) ($totals['expense']->total ?? 0),
                'count' => (int) (isset($filters['type']) ? ($totals[$filters['type']]->n ?? 0) : $totals->sum('n')),
            ],
        ];
    }

    /** Dữ liệu cho form: loại đang dùng, chiến dịch đang mở, danh bạ để chọn người. */
    public function options()
    {
        return [
            'categories' => Category::query()->whereNull('archived_at')->orderBy('type')->orderBy('sort_order')->orderBy('id')
                ->get()->map(fn (Category $c) => TransactionPresenter::category($c) + ['type' => $c->type->value]),
            'campaigns' => Campaign::query()->where('status', 'open')->orderByDesc('id')
                ->get(['id', 'name', 'category_id', 'amount_per_member']),
            'contacts' => Contact::query()->where('status', 'active')->orderBy('sort_order')->orderBy('full_name')
                ->get(['id', 'group_id', 'full_name', 'search_name'])
                ->map(fn (Contact $c) => ['id' => $c->id, 'name' => $c->full_name, 'search' => $c->search_name]),
        ];
    }

    public function show(Group $group, Transaction $transaction)
    {
        return TransactionPresenter::detail($transaction, $group);
    }

    public function store(TransactionRequest $request, Group $group)
    {
        $transaction = $this->writer->create(
            $group,
            $request->user(),
            $request->validated(),
            $request->file('photos', []),
            $request->file('bill'),
        );

        return response()->json(TransactionPresenter::detail($transaction, $group), 201);
    }

    /** POST thay vì PUT: PHP không đọc multipart của PUT. */
    public function update(TransactionRequest $request, Group $group, Transaction $transaction)
    {
        $this->writer->update(
            $transaction,
            $request->user(),
            $request->validated() + ['show_on_timeline' => $request->boolean('show_on_timeline', $transaction->show_on_timeline)],
            $request->file('photos', []),
            array_map('intval', $request->input('remove_photo_ids', [])),
            $request->has('photo_order') ? $request->input('photo_order') : null,
            $request->file('bill'),
            array_map('intval', $request->input('remove_bill_ids', [])),
        );

        return TransactionPresenter::detail($transaction->refresh(), $group);
    }

    public function destroy(Request $request, Group $group, Transaction $transaction)
    {
        $this->writer->delete($transaction, $request->user());

        return response()->noContent();
    }

    public function restore(Request $request, Group $group, Transaction $transaction)
    {
        $this->writer->restore($transaction, $request->user());

        return TransactionPresenter::detail($transaction->refresh(), $group);
    }
}
