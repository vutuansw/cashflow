<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignMember;
use App\Models\Contact;
use App\Models\Group;
use App\Models\SepayTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transactions\GroupStats;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Tạo/sửa chiến dịch, danh sách phải đóng, mã nội dung chuyển khoản. Xem plan/05 mục 1. */
class CampaignService
{
    public function __construct(private readonly GroupStats $stats) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{contact_id: int, expected_amount?: ?int, exempt?: bool}>|null  $members  null = mọi người đang tham gia
     */
    public function create(Group $group, User $user, array $data, ?array $members = null): Campaign
    {
        $campaign = DB::connection('groups')->transaction(function () use ($group, $user, $data, $members) {
            $campaign = Campaign::forceCreate([
                'group_id' => $group->id,
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'slug' => $this->slug($data['name']),
                'transfer_code' => strtoupper($data['transfer_code'] ?? '') ?: self::generateCode($group, $data['starts_on'] ?? null),
                'amount_per_member' => $data['amount_per_member'] ?? null,
                'target_amount' => $data['target_amount'] ?? null,
                'starts_on' => $data['starts_on'] ?? now()->toDateString(),
                'ends_on' => $data['ends_on'] ?? null,
                'status' => 'open',
                'description' => $data['description'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->syncMembers($campaign, $members ?? Contact::query()->where('status', 'active')->pluck('id')->map(fn ($id) => ['contact_id' => $id])->all());

            return $campaign;
        });

        $this->stats->forget($group);

        return $campaign;
    }

    public function update(Campaign $campaign, array $data): Campaign
    {
        if (isset($data['transfer_code']) && strtoupper($data['transfer_code']) !== $campaign->transfer_code) {
            if ($this->hasActivity($campaign)) {
                throw ValidationException::withMessages(['transfer_code' => 'Chiến dịch đã có giao dịch nên không đổi mã chuyển khoản được nữa.']);
            }
            $data['transfer_code'] = strtoupper($data['transfer_code']);
        }

        $campaign->fill(array_intersect_key($data, array_flip([
            'name', 'category_id', 'transfer_code', 'amount_per_member', 'target_amount', 'starts_on', 'ends_on', 'status', 'description',
        ])))->save();

        $this->stats->forget($campaign->group_id);

        return $campaign;
    }

    /**
     * Ghi lại danh sách phải đóng: người không có trong danh sách mới bị bỏ ra,
     * người còn lại giữ số tiền riêng/miễn theo dữ liệu gửi lên.
     *
     * @param  list<array{contact_id: int, expected_amount?: ?int, exempt?: bool}>  $members
     */
    public function syncMembers(Campaign $campaign, array $members): void
    {
        $valid = Contact::query()->whereIn('id', array_column($members, 'contact_id'))->pluck('id')->flip();
        $keep = [];

        foreach ($members as $m) {
            if (! isset($valid[$m['contact_id']])) {
                continue;
            }
            $keep[] = $m['contact_id'];
            CampaignMember::updateOrCreate(
                ['campaign_id' => $campaign->id, 'contact_id' => $m['contact_id']],
                ['expected_amount' => $m['expected_amount'] ?? null, 'exempt' => (bool) ($m['exempt'] ?? false)],
            );
        }

        CampaignMember::where('campaign_id', $campaign->id)->whereNotIn('contact_id', $keep)->delete();
        $this->stats->forget($campaign->group_id);
    }

    public function delete(Campaign $campaign): void
    {
        if ($this->hasActivity($campaign)) {
            throw ValidationException::withMessages(['campaign' => 'Chiến dịch đã có phiếu thu hoặc giao dịch SePay, chỉ đóng chiến dịch được chứ không xóa được.']);
        }
        $campaign->delete();
        $this->stats->forget($campaign->group_id);
    }

    public function hasActivity(Campaign $campaign): bool
    {
        return Transaction::withTrashed()->where('campaign_id', $campaign->id)->exists()
            || SepayTransaction::where('matched_campaign_id', $campaign->id)->exists();
    }

    /**
     * Mã nội dung chuyển khoản: chữ in hoa + số, 6-12 ký tự, duy nhất toàn hệ
     * thống. Từ mã nhóm + năm, vd chi-doan-a12 năm 2026 thành CHIDOA26.
     * Ngân hàng hay bỏ dấu cách, gạch, ký tự đặc biệt nên chỉ dùng chữ và số.
     */
    public static function generateCode(Group $group, ?string $startsOn = null): string
    {
        $prefix = substr(strtoupper((string) preg_replace('/[^a-z0-9]/i', '', Str::ascii($group->code))), 0, 6);
        $prefix = str_pad($prefix, 4, 'Q');
        $year = substr($startsOn ? substr($startsOn, 0, 4) : (string) now()->year, 2);

        $code = $prefix.$year;
        $attempt = 0;
        while (Campaign::acrossGroups()->where('transfer_code', $code)->exists()) {
            $code = $prefix.$year.strtoupper(Str::random(2 + intdiv($attempt++, 5)));
            $code = (string) preg_replace('/[^A-Z0-9]/', '', $code);
        }

        return substr($code, 0, 12);
    }

    private function slug(string $name): string
    {
        $base = Str::slug(Str::ascii($name)) ?: 'chien-dich';
        $base = substr($base, 0, 70);
        $slug = $base;
        $i = 2;
        while (Campaign::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-".$i++;
        }

        return $slug;
    }
}
