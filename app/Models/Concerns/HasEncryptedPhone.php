<?php

namespace App\Models\Concerns;

use App\Support\PhoneHash;
use App\Support\PhoneNumber;
use Illuminate\Validation\ValidationException;

/**
 * Cột phone lưu mã hóa (cast encrypted), cột phone_hash là HMAC để tra cứu.
 * Gán $model->phone là đủ: khi lưu, SĐT được chuẩn hóa rồi tính lại hash.
 * Xem plan/01 mục 3.
 */
trait HasEncryptedPhone
{
    public static function bootHasEncryptedPhone(): void
    {
        static::saving(function ($model) {
            if (! $model->isDirty('phone')) {
                return;
            }

            $raw = $model->phone;
            if ($raw === null || trim((string) $raw) === '') {
                $model->phone = null;
                $model->phone_hash = null;

                return;
            }

            $normalized = PhoneNumber::normalize($raw)
                ?? throw ValidationException::withMessages(['phone' => 'Số điện thoại không hợp lệ.']);

            $model->phone = $normalized;
            $model->phone_hash = PhoneHash::ofNormalized($normalized);
        });
    }

    public function scopeWherePhone($query, ?string $phone)
    {
        $hash = PhoneHash::make($phone);

        return $hash === null ? $query->whereRaw('1 = 0') : $query->where('phone_hash', $hash);
    }

    public function maskedPhone(): ?string
    {
        return PhoneNumber::mask($this->phone);
    }
}
