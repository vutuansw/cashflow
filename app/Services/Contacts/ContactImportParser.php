<?php

namespace App\Services\Contacts;

use App\Models\Contact;
use App\Support\PhoneHash;
use App\Support\PhoneNumber;
use App\Support\VietnameseText;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Đọc danh bạ dán từ Excel/Google Sheets: mỗi dòng một người, các cột
 * "Họ tên, SĐT, Ngày sinh, Địa chỉ" cách bằng tab (dán từ bảng tính) hoặc dấu
 * phẩy (gõ tay; phần sau dấu phẩy thứ ba là địa chỉ). Xem plan/02 mục 4.
 *
 * Chỉ đọc và kiểm tra, không lưu: màn hình xem trước cho QTV sửa trước.
 */
class ContactImportParser
{
    public const MAX_ROWS = 500;

    /**
     * @param  int  $groupId  để báo trùng với danh bạ đang có
     * @return list<array{line: int, full_name: string, phone: ?string, birthday: ?string, address: ?string, errors: list<string>}>
     */
    public function parse(string $text, int $groupId): array
    {
        $rows = [];
        $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '' || ($index === 0 && $this->looksLikeHeader($line))) {
                continue;
            }

            $cells = str_contains($line, "\t") ? explode("\t", $line) : explode(',', $line, 4);
            $cells = array_map(fn ($c) => trim($c, " \t\"'"), $cells);

            $rows[] = ['line' => $index + 1] + $this->row($cells[0] ?? '', $cells[1] ?? '', $cells[2] ?? '', $cells[3] ?? '');

            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
        }

        return $this->markDuplicates($rows, $groupId);
    }

    /**
     * Kiểm tra lại các dòng QTV đã sửa ở màn hình xem trước trước khi lưu.
     *
     * @param  list<array{full_name?: ?string, phone?: ?string, birthday?: ?string, address?: ?string}>  $input
     */
    public function validateRows(array $input, int $groupId): array
    {
        $rows = [];
        foreach (array_values($input) as $i => $item) {
            $rows[] = ['line' => $i + 1] + $this->row(
                (string) ($item['full_name'] ?? ''),
                (string) ($item['phone'] ?? ''),
                (string) ($item['birthday'] ?? ''),
                (string) ($item['address'] ?? ''),
            );
        }

        return $this->markDuplicates($rows, $groupId);
    }

    private function row(string $name, string $phone, string $birthday, string $address): array
    {
        $errors = [];
        $name = preg_replace('/\s+/u', ' ', trim($name)) ?? '';

        if ($name === '') {
            $errors[] = 'Thiếu họ tên.';
        } elseif (mb_strlen($name) > 120) {
            $errors[] = 'Họ tên dài quá 120 ký tự.';
        }

        $normalizedPhone = null;
        if (trim($phone) !== '') {
            $normalizedPhone = PhoneNumber::normalize($this->restoreLeadingZero($phone));
            if ($normalizedPhone === null) {
                $errors[] = "Số điện thoại \"{$phone}\" không hợp lệ.";
            }
        }

        $date = null;
        if (trim($birthday) !== '') {
            $date = $this->parseDate($birthday);
            if ($date === null) {
                $errors[] = "Ngày sinh \"{$birthday}\" không đọc được, dùng dạng ngày/tháng/năm.";
            }
        }

        return [
            'full_name' => $name,
            'phone' => $normalizedPhone ?? (trim($phone) === '' ? null : trim($phone)),
            'birthday' => $date,
            'address' => trim($address) === '' ? null : mb_substr(trim($address), 0, 255),
            'errors' => $errors,
        ];
    }

    /** Excel hay làm mất số 0 đầu: 988777222 thành 0988777222. */
    private function restoreLeadingZero(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return strlen($digits) === 9 && ! str_starts_with(trim($phone), '+') ? '0'.$digits : $phone;
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);

        // Ô ngày trong Excel dán ra dạng số sê-ri (số ngày kể từ 30/12/1899).
        if (preg_match('/^\d{5}$/', $value)) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        }

        foreach (['d/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y', 'Y-m-d'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }

            // So lại chuỗi để loại ngày tràn như 31/02 mà PHP tự đẩy sang tháng sau.
            if ($date !== null && $date->format($format) === $value && $date->year >= 1900 && $date->isPast()) {
                return $date->toDateString();
            }
        }

        return null;
    }

    private function looksLikeHeader(string $line): bool
    {
        $folded = VietnameseText::fold($line);

        return str_contains($folded, 'ho ten') || str_contains($folded, 'ho va ten') || str_starts_with($folded, 'ten ');
    }

    /** Báo trùng SĐT với danh bạ đang có và giữa các dòng với nhau. */
    private function markDuplicates(array $rows, int $groupId): array
    {
        $hashes = [];
        foreach ($rows as $i => $row) {
            if ($row['phone'] !== null && PhoneNumber::normalize($row['phone']) !== null) {
                $hashes[$i] = PhoneHash::ofNormalized($row['phone']);
            }
        }

        $existing = Contact::acrossGroups()
            ->where('group_id', $groupId)
            ->whereIn('phone_hash', array_values($hashes))
            ->pluck('full_name', 'phone_hash');

        $seen = [];
        foreach ($hashes as $i => $hash) {
            if (isset($existing[$hash])) {
                $rows[$i]['errors'][] = "SĐT đã có trong danh bạ ({$existing[$hash]}).";
            } elseif (isset($seen[$hash])) {
                $rows[$i]['errors'][] = "SĐT trùng với dòng {$rows[$seen[$hash]]['line']}.";
            }
            $seen[$hash] ??= $i;
        }

        return $rows;
    }
}
