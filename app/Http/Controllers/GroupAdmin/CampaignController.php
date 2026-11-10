<?php

namespace App\Http\Controllers\GroupAdmin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Group;
use App\Services\Campaigns\CampaignProgress;
use App\Services\Campaigns\CampaignService;
use App\Services\Transactions\TransactionWriter;
use App\Support\CurrentGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Chiến dịch thu trong quản trị nhóm, xem plan/05 mục 1. */
class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignService $service,
        private readonly CampaignProgress $progress,
    ) {}

    public function index()
    {
        return Campaign::query()->orderByRaw("status = 'open' desc")->orderByDesc('id')->get()
            ->map(fn (Campaign $c) => $this->summary($c) + array_diff_key($this->progress->of($c), ['people' => true]));
    }

    /** Dữ liệu cho form: loại thu, danh bạ đang tham gia, mã chuyển khoản gợi ý. */
    public function options(Group $group)
    {
        return [
            'categories' => Category::query()->where('type', 'income')->whereNull('archived_at')->orderBy('sort_order')->get(['id', 'group_id', 'name', 'icon', 'color']),
            'contacts' => Contact::query()->where('status', 'active')->orderBy('sort_order')->orderBy('full_name')->get(['id', 'group_id', 'full_name']),
            'suggested_code' => CampaignService::generateCode($group),
            'has_bank' => $group->bank_code !== null && $group->bank_account_no !== null,
        ];
    }

    public function store(Request $request, Group $group)
    {
        $data = $this->validated($request);
        $members = isset($data['exempt_ids']) ? $this->membersFromExempt(null, $data['exempt_ids']) : ($data['members'] ?? null);
        $campaign = $this->service->create($group, $request->user(), $data, $members);

        return response()->json($this->present($campaign), 201);
    }

    public function show(Group $group, Campaign $campaign)
    {
        return $this->present($campaign);
    }

    private function present(Campaign $campaign): array
    {
        $progress = $this->progress->of($campaign);

        // QTV thấy SĐT người chưa đóng để bấm gọi nhắc (plan/05 mục 1); trang công khai thì không.
        $phones = Contact::query()->whereIn('id', array_filter(array_column($progress['people'], 'contact_id')))
            ->get(['id', 'group_id', 'phone'])->mapWithKeys(fn (Contact $c) => [$c->id => $c->phone]);
        $members = CampaignMember::where('campaign_id', $campaign->id)->get()->keyBy('contact_id');

        $progress['people'] = array_map(fn ($p) => $p + [
            'phone' => $p['contact_id'] ? ($phones[$p['contact_id']] ?? null) : null,
            'member_expected_amount' => $p['contact_id'] ? $members->get($p['contact_id'])?->expected_amount : null,
        ], $progress['people']);

        return $this->summary($campaign) + $progress + ['locked_code' => $this->service->hasActivity($campaign)];
    }

    public function update(Request $request, Group $group, Campaign $campaign)
    {
        $data = $this->validated($request, $campaign);
        $this->service->update($campaign, $data);
        if (isset($data['exempt_ids'])) {
            $this->service->syncMembers($campaign, $this->membersFromExempt($campaign, $data['exempt_ids']));
        } elseif (array_key_exists('members', $data) && $data['members'] !== null) {
            $this->service->syncMembers($campaign, $data['members']);
        }

        return $this->present($campaign->refresh());
    }

    public function destroy(Group $group, Campaign $campaign)
    {
        $this->service->delete($campaign);

        return response()->noContent();
    }

    /** Ghi nhận một người đóng tiền mặt: tạo phiếu thu gắn chiến dịch và người đó. */
    public function cash(Request $request, Group $group, Campaign $campaign, TransactionWriter $writer)
    {
        $data = $request->validate([
            'contact_id' => ['required', 'integer'],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'occurred_at' => ['nullable', 'date'],
        ]);
        if (! Contact::whereKey($data['contact_id'])->exists()) {
            throw ValidationException::withMessages(['contact_id' => 'Người này không có trong danh bạ nhóm.']);
        }

        $writer->create($group, $request->user(), [
            'type' => 'income',
            'category_id' => $campaign->category_id,
            'campaign_id' => $campaign->id,
            'contact_id' => $data['contact_id'],
            'amount' => $data['amount'],
            'occurred_at' => $data['occurred_at'] ?? now()->format('Y-m-d H:i'),
            'description' => 'Đóng tiền mặt',
        ]);

        return $this->present($campaign);
    }

    /**
     * Mọi người đang tham gia đều phải đóng, trừ người được miễn (plan/05 mục 1).
     * Sửa chiến dịch thì giữ người đã có trong danh sách (kể cả đã rời nhóm) và số tiền riêng của họ.
     *
     * @param  list<int>  $exemptIds
     */
    private function membersFromExempt(?Campaign $campaign, array $exemptIds): array
    {
        $exempt = array_flip(array_map('intval', $exemptIds));
        $existing = $campaign ? CampaignMember::where('campaign_id', $campaign->id)->get()->keyBy('contact_id') : collect();
        $ids = Contact::query()->where('status', 'active')->pluck('id')->merge($existing->keys())->unique();

        return $ids->map(fn ($id) => [
            'contact_id' => (int) $id,
            'exempt' => isset($exempt[$id]),
            'expected_amount' => $existing->get($id)?->expected_amount,
        ])->values()->all();
    }

    private function summary(Campaign $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'transfer_code' => $c->transfer_code,
            'category_id' => $c->category_id,
            'amount_per_member' => $c->amount_per_member,
            'target_amount' => $c->target_amount,
            'starts_on' => $c->starts_on?->toDateString(),
            'ends_on' => $c->ends_on?->toDateString(),
            'status' => $c->status,
            'description' => $c->description,
        ];
    }

    private function validated(Request $request, ?Campaign $campaign = null): array
    {
        if ($request->filled('transfer_code')) {
            $request->merge(['transfer_code' => strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $request->input('transfer_code')))]);
        }
        $groupId = app(CurrentGroup::class)->id();

        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('campaigns', 'name')->where('group_id', $groupId)->ignore($campaign?->id)],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('group_id', $groupId)->where('type', 'income')],
            'transfer_code' => ['nullable', 'string', 'regex:/^[A-Z0-9]{6,12}$/', Rule::unique('campaigns', 'transfer_code')->ignore($campaign?->id)],
            'amount_per_member' => ['nullable', 'integer', 'min:1000', 'max:999999999999'],
            'target_amount' => ['nullable', 'integer', 'min:1000', 'max:999999999999'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => [$campaign ? 'required' : 'nullable', 'in:open,closed'],
            'description' => ['nullable', 'string', 'max:5000'],
            'members' => ['nullable', 'array', 'max:2000'],
            'members.*.contact_id' => ['required', 'integer'],
            'members.*.expected_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'members.*.exempt' => ['nullable', 'boolean'],
            'exempt_ids' => ['nullable', 'array', 'max:2000'],
            'exempt_ids.*' => ['integer'],
        ], [
            'name.unique' => 'Nhóm đã có chiến dịch tên này, đặt tên khác nhé.',
            'transfer_code.regex' => 'Mã chuyển khoản gồm 6–12 chữ cái không dấu hoặc số, viết liền.',
            'transfer_code.unique' => 'Mã chuyển khoản này đã có chiến dịch khác dùng.',
            'ends_on.after_or_equal' => 'Ngày kết thúc phải sau ngày bắt đầu.',
        ], ['name' => 'tên chiến dịch', 'transfer_code' => 'mã chuyển khoản', 'amount_per_member' => 'số tiền mỗi người', 'target_amount' => 'mục tiêu']);
    }
}
