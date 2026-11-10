<?php

namespace App\Jobs;

use App\Mail\SepayDigestMail;
use App\Models\Group;
use App\Models\PaymentClaim;
use App\Models\SepayTransaction;
use App\Support\CurrentGroup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Email cho QTV khi có giao dịch SePay hoặc bill thành viên mới vào hàng chờ, gộp các giao dịch tới
 * trong lúc chờ (tối đa một email mỗi 15 phút mỗi nhóm). Gửi tới notify_emails
 * của nhóm, trống thì gửi trưởng nhóm. Xem plan/03 mục 8.
 */
class SendSepayDigest implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $groupId) {}

    public function handle(): void
    {
        $group = Group::query()->find($this->groupId);
        if ($group === null || ($group->notify_settings['sepay'] ?? true) === false) {
            return;
        }

        // Tên chiến dịch, người gửi đọc qua global scope nhóm.
        app(CurrentGroup::class)->set($group);

        $sinceKey = "sepay-digest-since:{$group->id}";
        $since = Cache::get($sinceKey, now()->subDay()->toIso8601String());

        $items = SepayTransaction::acrossGroups()
            ->where('group_id', $group->id)
            ->where('status', 'pending')
            ->where('created_at', '>', $since)
            ->orderBy('transaction_date')
            ->get();

        // Bill thành viên tự gửi (plan/05 mục 5) đi chung email.
        $claims = PaymentClaim::acrossGroups()
            ->where('group_id', $group->id)
            ->where('status', 'pending')
            ->where('created_at', '>', $since)
            ->with('campaign:id,name', 'contact:id,full_name')
            ->orderBy('created_at')
            ->get();

        Cache::forever($sinceKey, now()->toIso8601String());
        if ($items->isEmpty() && $claims->isEmpty()) {
            return;
        }

        $to = array_values(array_filter($group->notify_emails ?? []));
        if ($to === [] && $group->owner?->email) {
            $to = [$group->owner->email];
        }
        if ($to === []) {
            return;
        }

        Mail::to($to)->send(new SepayDigestMail($group, $items, $claims));
    }
}
