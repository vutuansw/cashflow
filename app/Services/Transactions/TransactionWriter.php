<?php

namespace App\Services\Transactions;

use App\Models\BillScan;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Group;
use App\Models\PaymentClaim;
use App\Models\SepayTransaction;
use App\Models\Transaction;
use App\Models\TransactionHistory;
use App\Models\TransactionPhoto;
use App\Models\User;
use App\Services\Media\ImageStore;
use App\Services\Payments\DuplicateFinder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Tạo, sửa, xóa mềm, khôi phục phiếu thu chi. Mọi thay đổi ghi vào
 * transaction_histories và làm mới số liệu tổng hợp. Xem plan/03 mục 2.
 *
 * Ảnh được xử lý và ghi ra đĩa trước, rồi mới ghi database trong một giao
 * dịch; nếu database lỗi thì xóa lại file vừa ghi để không sinh ảnh mồ côi.
 */
class TransactionWriter
{
    /** Các trường được ghi vào lịch sử khi đổi. */
    private const TRACKED = [
        'type', 'category_id', 'campaign_id', 'contact_id', 'amount', 'occurred_at', 'title',
        'description', 'payer_name', 'show_on_timeline',
    ];

    public function __construct(
        private readonly ImageStore $images,
        private readonly GroupStats $stats,
        private readonly DuplicateFinder $duplicates,
    ) {}

    /**
     * @param  array<string, mixed>  $data  đã kiểm tra bởi TransactionRequest
     * @param  list<UploadedFile>  $photos
     */
    public function create(Group $group, User $user, array $data, array $photos = [], ?UploadedFile $bill = null): Transaction
    {
        $stored = $this->storePhotos($group, $photos);
        $billPath = $bill ? $this->images->storeBill($group, $bill) : null;

        try {
            $transaction = DB::connection('groups')->transaction(function () use ($group, $user, $data, $stored, $billPath) {
                $scan = $this->scan($data['bill_scan_id'] ?? null);
                $sepay = empty($data['sepay_transaction_id']) ? null : SepayTransaction::lockForUpdate()->find($data['sepay_transaction_id']);
                if ($sepay !== null && ($sepay->status !== 'pending' || Transaction::withTrashed()->where('sepay_transaction_id', $sepay->id)->exists())) {
                    throw ValidationException::withMessages(['sepay_transaction_id' => 'Giao dịch SePay này đã được duyệt hoặc bỏ qua.']);
                }
                // Duyệt bill thành viên gửi (plan/05 mục 5): ảnh bill của yêu cầu thành chứng từ của phiếu.
                $claim = empty($data['payment_claim_id']) ? null : PaymentClaim::lockForUpdate()->find($data['payment_claim_id']);
                if ($claim !== null) {
                    if ($claim->status !== 'pending') {
                        throw ValidationException::withMessages(['payment_claim_id' => 'Bill này đã được duyệt hoặc bỏ qua.']);
                    }
                    $scan = $claim->scan ?? $scan;
                }

                $transaction = Transaction::forceCreate($this->attributes($data) + [
                    'group_id' => $group->id,
                    'source' => $sepay ? 'sepay' : ($scan?->status === 'done' && $scan->model !== null ? 'ai_bill' : 'manual'),
                    'sepay_transaction_id' => $sepay?->id,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                $this->attachPhotos($transaction, $stored, 0);
                $this->attachBill($transaction, $user, $scan, $billPath);

                $sepay?->forceFill(['status' => 'approved', 'reviewed_by' => $user->id, 'reviewed_at' => now()])->save();
                $claim?->forceFill(['status' => 'approved', 'transaction_id' => $transaction->id, 'reviewed_by' => $user->id, 'reviewed_at' => now()])->save();

                // Chiều ngược lại: bill thành viên đang chờ cho đúng lần chuyển này thì gắn luôn vào phiếu.
                if ($sepay !== null) {
                    foreach ($this->duplicates->claimsFor($transaction, $sepay) as $other) {
                        if ($other->id !== $claim?->id) {
                            $other->scan?->update(['transaction_id' => $transaction->id]);
                            $other->forceFill(['status' => 'approved', 'transaction_id' => $transaction->id, 'reviewed_by' => $user->id, 'reviewed_at' => now()])->save();
                        }
                    }
                }

                $this->log($transaction, $user, 'created', ['after' => $this->snapshot($transaction)]);

                return $transaction;
            });
        } catch (Throwable $e) {
            $this->images->delete([...array_column($stored, 'path'), ...array_column($stored, 'thumb_path'), $billPath]);
            throw $e;
        }

        $this->stats->forget($group);

        return $transaction;
    }

    /**
     * @param  list<UploadedFile>  $photos  ảnh mới thêm
     * @param  list<int>  $removePhotoIds
     * @param  list<string>|null  $order  thứ tự ảnh sau khi sửa: "p:{id}" ảnh cũ, "n:{i}" ảnh mới thứ i
     * @param  list<int>  $removeBillIds
     */
    public function update(
        Transaction $transaction,
        User $user,
        array $data,
        array $photos = [],
        array $removePhotoIds = [],
        ?array $order = null,
        ?UploadedFile $bill = null,
        array $removeBillIds = [],
    ): Transaction {
        $group = $transaction->group()->firstOrFail();
        $stored = $this->storePhotos($group, $photos);
        $billPath = $bill ? $this->images->storeBill($group, $bill) : null;
        $deleteFiles = [];

        try {
            DB::connection('groups')->transaction(function () use ($transaction, $user, $data, $stored, $billPath, $removePhotoIds, $order, $removeBillIds, &$deleteFiles) {
                $before = $this->snapshot($transaction);

                $transaction->fill($this->attributes($data) + ['updated_by' => $user->id])->save();

                $photoChanges = [];
                $removed = $transaction->photos()->whereIn('id', $removePhotoIds)->get();
                foreach ($removed as $photo) {
                    $deleteFiles[] = $photo->path;
                    $deleteFiles[] = $photo->thumb_path;
                    $photo->delete();
                }
                if ($removed->isNotEmpty()) {
                    $photoChanges['photos_removed'] = $removed->count();
                }

                $new = $this->attachPhotos($transaction, $stored, (int) TransactionPhoto::where('transaction_id', $transaction->id)->max('sort_order') + 1);
                if ($new !== []) {
                    $photoChanges['photos_added'] = count($new);
                }
                if ($order !== null) {
                    $this->reorder($transaction, $order, $new);
                }

                $removedBills = BillScan::where('transaction_id', $transaction->id)->whereIn('id', $removeBillIds)->get();
                foreach ($removedBills as $scan) {
                    $deleteFiles[] = $scan->path;
                    $scan->update(['transaction_id' => null, 'path' => null]);
                }
                if ($removedBills->isNotEmpty()) {
                    $photoChanges['bills_removed'] = $removedBills->count();
                }

                $scan = $this->scan($data['bill_scan_id'] ?? null);
                if ($scan !== null || $billPath !== null) {
                    $this->attachBill($transaction, $user, $scan, $billPath);
                    $photoChanges['bills_added'] = 1;
                }

                $after = $this->snapshot($transaction->refresh());
                $diff = $this->diff($before, $after);

                if ($diff !== [] || $photoChanges !== []) {
                    $this->log($transaction, $user, 'updated', array_filter([
                        'before' => array_intersect_key($before, $diff),
                        'after' => $diff,
                        'media' => $photoChanges,
                    ]));
                }
            });
        } catch (Throwable $e) {
            $this->images->delete([...array_column($stored, 'path'), ...array_column($stored, 'thumb_path'), $billPath]);
            throw $e;
        }

        // Chỉ xóa file cũ khi database đã ghi xong.
        $this->images->delete($deleteFiles);
        $this->stats->forget($transaction->group_id);

        return $transaction;
    }

    /**
     * Gắn bill thành viên vào phiếu đã có cho cùng lần chuyển (plan/05 mục 5):
     * không cộng thêm tiền, chỉ thêm chứng từ và đóng yêu cầu lại.
     */
    public function attachClaim(Transaction $transaction, User $user, PaymentClaim $claim): Transaction
    {
        DB::connection('groups')->transaction(function () use ($transaction, $user, $claim) {
            $claim = PaymentClaim::lockForUpdate()->findOrFail($claim->id);
            if ($claim->status !== 'pending') {
                throw ValidationException::withMessages(['payment_claim_id' => 'Bill này đã được duyệt hoặc bỏ qua.']);
            }
            $claim->scan?->update(['transaction_id' => $transaction->id]);
            $claim->forceFill(['status' => 'approved', 'transaction_id' => $transaction->id, 'reviewed_by' => $user->id, 'reviewed_at' => now()])->save();
            if ($claim->scan !== null) {
                $this->log($transaction, $user, 'updated', ['media' => ['bills_added' => 1]]);
            }
        });

        return $transaction;
    }

    public function delete(Transaction $transaction, User $user): void
    {
        DB::connection('groups')->transaction(function () use ($transaction, $user) {
            // Phiếu từ SePay: bỏ liên kết để giao dịch quay về hàng chờ (plan/05 mục 4);
            // nhớ id trong lịch sử để khôi phục phiếu thì gắn lại.
            $sepayId = $transaction->sepay_transaction_id;
            $transaction->forceFill(['deleted_by' => $user->id, 'sepay_transaction_id' => null])->save();
            $transaction->delete();
            if ($sepayId) {
                SepayTransaction::whereKey($sepayId)->update(['status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null]);
            }
            $this->log($transaction, $user, 'deleted', $sepayId ? ['sepay_transaction_id' => $sepayId] : null);
        });

        $this->stats->forget($transaction->group_id);
    }

    public function restore(Transaction $transaction, User $user): void
    {
        $days = config('cashflow.restore_days');
        if ($transaction->deleted_at === null) {
            return;
        }
        if ($transaction->deleted_at->lt(now()->subDays($days))) {
            throw ValidationException::withMessages(['transaction' => "Phiếu đã xóa quá {$days} ngày, không khôi phục được."]);
        }

        DB::connection('groups')->transaction(function () use ($transaction, $user) {
            $transaction->restore();
            $attributes = ['deleted_by' => null, 'updated_by' => $user->id];

            // Gắn lại giao dịch SePay nếu nó vẫn đang chờ (chưa ai duyệt thành phiếu khác).
            $sepayId = TransactionHistory::where('transaction_id', $transaction->id)->where('action', 'deleted')->latest('id')->value('changes')['sepay_transaction_id'] ?? null;
            $sepay = $sepayId ? SepayTransaction::lockForUpdate()->find($sepayId) : null;
            if ($sepay?->status === 'pending' && ! Transaction::withTrashed()->where('sepay_transaction_id', $sepay->id)->exists()) {
                $attributes['sepay_transaction_id'] = $sepay->id;
                $sepay->forceFill(['status' => 'approved', 'reviewed_by' => $user->id, 'reviewed_at' => now()])->save();
            }

            $transaction->forceFill($attributes)->save();
            $this->log($transaction, $user, 'restored', null);
        });

        $this->stats->forget($transaction->group_id);
    }

    /** Tiêu đề trống thì gợi ý "{Loại} {tên người}", vd "Thăm ốm bạn Lan". */
    public static function suggestTitle(Category $category, ?string $person): string
    {
        return trim($category->name.' '.($person ?? ''));
    }

    private function attributes(array $data): array
    {
        $attributes = array_intersect_key($data, array_flip(self::TRACKED));

        if (($data['type'] ?? null) !== 'income') {
            $attributes['campaign_id'] = null;
        }

        $contact = empty($data['contact_id']) ? null : Contact::find($data['contact_id']);
        $attributes['contact_id'] = $contact?->id;
        // Người trong danh bạ thì không cần lưu tên tự do nữa.
        $attributes['payer_name'] = $contact ? null : (trim((string) ($data['payer_name'] ?? '')) ?: null);

        if (trim((string) ($data['title'] ?? '')) === '') {
            $category = Category::findOrFail($data['category_id']);
            $attributes['title'] = mb_substr(self::suggestTitle($category, $contact?->full_name ?? $attributes['payer_name']), 0, 150);
        } else {
            $attributes['title'] = trim($data['title']);
        }

        if (array_key_exists('description', $attributes)) {
            $attributes['description'] = trim((string) $attributes['description']) ?: null;
        }

        return $attributes;
    }

    /** @return list<array{path: string, thumb_path: string, width: int, height: int}> */
    private function storePhotos(Group $group, array $photos): array
    {
        $stored = [];
        try {
            foreach ($photos as $file) {
                $stored[] = $this->images->storeEventPhoto($group, $file);
            }
        } catch (Throwable $e) {
            $this->images->delete([...array_column($stored, 'path'), ...array_column($stored, 'thumb_path')]);
            throw $e;
        }

        return $stored;
    }

    /** @return list<TransactionPhoto> */
    private function attachPhotos(Transaction $transaction, array $stored, int $start): array
    {
        $photos = [];
        foreach ($stored as $i => $file) {
            $photos[] = $transaction->photos()->create($file + ['sort_order' => $start + $i]);
        }

        return $photos;
    }

    /** @param  list<TransactionPhoto>  $new */
    private function reorder(Transaction $transaction, array $order, array $new): void
    {
        $existing = $transaction->photos()->get()->keyBy('id');
        $position = 0;
        $seen = [];

        foreach ($order as $token) {
            [$kind, $key] = array_pad(explode(':', $token, 2), 2, null);
            $photo = $kind === 'p' ? $existing->get((int) $key) : ($kind === 'n' ? ($new[(int) $key] ?? null) : null);
            if ($photo !== null && ! isset($seen[$photo->id])) {
                $photo->update(['sort_order' => $position++]);
                $seen[$photo->id] = true;
            }
        }

        // Ảnh không nằm trong danh sách thứ tự thì xếp sau cùng.
        foreach ($transaction->photos()->get() as $photo) {
            if (! isset($seen[$photo->id])) {
                $photo->update(['sort_order' => $position++]);
            }
        }
    }

    private function scan(?int $id): ?BillScan
    {
        return $id === null ? null : BillScan::find($id);
    }

    private function attachBill(Transaction $transaction, User $user, ?BillScan $scan, ?string $path): void
    {
        $scan?->update(['transaction_id' => $transaction->id]);

        if ($path !== null) {
            BillScan::forceCreate([
                'group_id' => $transaction->group_id,
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'path' => $path,
                'status' => 'done',
            ]);
        }
    }

    private function snapshot(Transaction $transaction): array
    {
        $values = [];
        foreach (self::TRACKED as $field) {
            $value = $transaction->getAttribute($field);
            $values[$field] = match (true) {
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i'),
                default => $value,
            };
        }

        return $values;
    }

    private function diff(array $before, array $after): array
    {
        return array_filter($after, fn ($value, $key) => $before[$key] != $value, ARRAY_FILTER_USE_BOTH);
    }

    private function log(Transaction $transaction, User $user, string $action, ?array $changes): void
    {
        TransactionHistory::create([
            'transaction_id' => $transaction->id,
            'user_id' => $user->id,
            'action' => $action,
            'changes' => $changes,
        ]);
    }
}
