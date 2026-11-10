<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Contact;
use App\Services\Groups\GroupViewer;
use Illuminate\Support\Str;

/**
 * Nội dung chuyển khoản điền sẵn vào QR và deeplink, theo người đang xem
 * trang đóng quỹ (plan/05 mục 1, chốt 10/11/2026). Cần tên người đóng hơn mã
 * chiến dịch:
 * - Không biết là ai: trả null, để app ngân hàng tự điền "TÊN CHỦ TK chuyen tien".
 * - Thành viên nhóm: "{TÊN} {MÃ}", ví dụ "VU DUC TUAN DT2026".
 */
final class TransferContent
{
    /** Một số ngân hàng cắt nội dung dài: giữ cả chuỗi trong giới hạn này. */
    public const MAX_LENGTH = 25;

    public static function forViewer(GroupViewer $viewer, Campaign $campaign): ?string
    {
        if (! $viewer->isMember()) {
            return null;
        }

        // Tên trong danh bạ (app so khớp theo tên này) rồi mới tới tên tài khoản.
        $contact = $viewer->member->contact_id ? Contact::query()->find($viewer->member->contact_id) : null;

        return self::build($contact?->full_name ?? $viewer->user?->name, $campaign->transfer_code);
    }

    /**
     * Tên in hoa không dấu đứng trước, mã chiến dịch giữ nguyên ở cuối. Quá
     * dài thì rút tên còn "đệm + tên", rồi chỉ còn tên. Không có tên thì null.
     */
    public static function build(?string $name, ?string $code): ?string
    {
        $words = array_values(array_filter(explode(' ', self::clean($name))));
        if ($words === []) {
            return null;
        }

        $code = self::clean($code);
        foreach ([count($words), 2, 1] as $keep) {
            $content = trim(implode(' ', array_slice($words, -$keep)).' '.$code);
            if (strlen($content) <= self::MAX_LENGTH) {
                return $content;
            }
        }

        return $content;
    }

    /** "Đỗ Văn Khánh" → "DO VAN KHANH"; chỉ chữ, số, dấu cách. */
    private static function clean(?string $text): string
    {
        $text = str_replace(['đ', 'Đ'], 'D', (string) $text);
        $text = Str::upper(Str::ascii($text));

        return trim((string) preg_replace('/[^A-Z0-9]+/', ' ', $text));
    }
}
