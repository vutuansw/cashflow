<?php

namespace App\Jobs;

use App\Mail\SepayDigestMail;
use App\Models\Group;
use App\Models\SepayTransaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Email cho QTV khi có giao dịch SePay mới vào hàng chờ, gộp các giao dịch tới
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

        $sinceKey = "sepay-digest-since:{$group->id}";
        $since = Cache::get($sinceKey, now()->subDay()->toIso8601String());

        $items = SepayTransaction::acrossGroups()
            ->where('group_id', $group->id)
            ->where('status', 'pending')
            ->where('created_at', '>', $since)
            ->orderBy('transaction_date')
            ->get();

        Cache::forever($sinceKey, now()->toIso8601String());
        if ($items->isEmpty()) {
            return;
        }

        $to = array_values(array_filter($group->notify_emails ?? []));
        if ($to === [] && $group->owner?->email) {
            $to = [$group->owner->email];
        }
        if ($to === []) {
            return;
        }

        Mail::to($to)->send(new SepayDigestMail($group, $items));
    }
}
