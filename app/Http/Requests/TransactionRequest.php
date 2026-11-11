<?php

namespace App\Http\Requests;

use App\Models\BillScan;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Contact;
use App\Models\PaymentClaim;
use App\Models\SepayTransaction;
use App\Models\Transaction;
use App\Models\TransactionPhoto;
use App\Services\Payments\DuplicateFinder;
use App\Services\Payments\PaymentClaimService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Form tạo/sửa phiếu thu chi (multipart vì có ảnh). Xem plan/03 mục 2.
 *
 * Loại, chiến dịch, người trong danh bạ và bill đều tra qua model có global
 * scope nhóm, nên id của nhóm khác coi như không tồn tại.
 */
class TransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if (is_string($this->input('amount'))) {
            // Ô tiền gửi lên có thể còn dấu chấm hàng nghìn: "1.500.000".
            $merge['amount'] = preg_replace('/\D/', '', $this->input('amount'));
        }
        foreach (['campaign_id', 'contact_id', 'bill_scan_id', 'sepay_transaction_id', 'payment_claim_id'] as $key) {
            if ($this->input($key) === '' || $this->input($key) === 'null') {
                $merge[$key] = null;
            }
        }
        $this->merge($merge);
    }

    public function rules(): array
    {
        $limit = config('cashflow.limits.photos_per_transaction');
        $maxKb = config('cashflow.limits.photo_upload_max_kb');
        $mimes = implode(',', config('cashflow.images.mimes'));

        return [
            'type' => ['required', 'in:income,expense'],
            'category_id' => ['required', 'integer'],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'occurred_at' => ['required', 'date', 'before_or_equal:'.now()->addDay()->toDateTimeString()],
            'title' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'contact_id' => ['nullable', 'integer'],
            'payer_name' => ['nullable', 'string', 'max:120'],
            'campaign_id' => ['nullable', 'integer'],
            'show_on_timeline' => ['sometimes', 'boolean'],
            'exclude_from_top' => ['sometimes', 'boolean'],
            'photos' => ['nullable', 'array', "max:{$limit}"],
            'photos.*' => ['file', "mimes:{$mimes}", "max:{$maxKb}"],
            'remove_photo_ids' => ['nullable', 'array'],
            'remove_photo_ids.*' => ['integer'],
            'photo_order' => ['nullable', 'array'],
            'photo_order.*' => ['string', 'max:20'],
            'bill' => ['nullable', 'file', "mimes:{$mimes}", "max:{$maxKb}"],
            'bill_scan_id' => ['nullable', 'integer'],
            'sepay_transaction_id' => ['nullable', 'integer'],
            'payment_claim_id' => ['nullable', 'integer'],
            'remove_bill_ids' => ['nullable', 'array'],
            'remove_bill_ids.*' => ['integer'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $current = $this->route('transaction');
            $current = $current instanceof Transaction ? $current : null;

            $category = Category::find($this->integer('category_id'));
            if ($category === null || $category->type->value !== $this->input('type')) {
                $validator->errors()->add('category_id', 'Chọn loại thu chi của nhóm, đúng với phiếu thu hay phiếu chi.');
            } elseif ($category->archived_at !== null && $current?->category_id !== $category->id) {
                $validator->errors()->add('category_id', 'Loại này đã ẩn, chọn loại khác.');
            }

            if ($this->filled('contact_id') && ! Contact::whereKey($this->integer('contact_id'))->exists()) {
                $validator->errors()->add('contact_id', 'Người này không có trong danh bạ nhóm.');
            }

            if ($this->filled('campaign_id')) {
                $campaign = Campaign::find($this->integer('campaign_id'));
                if ($this->input('type') !== 'income') {
                    $validator->errors()->add('campaign_id', 'Chỉ phiếu thu mới gắn với chiến dịch.');
                } elseif ($campaign === null || ($campaign->status !== 'open' && $current?->campaign_id !== $campaign->id)) {
                    $validator->errors()->add('campaign_id', 'Chọn một chiến dịch đang mở của nhóm.');
                }
            }

            if ($this->filled('bill_scan_id')) {
                $scan = BillScan::find($this->integer('bill_scan_id'));
                if ($scan === null || ($scan->transaction_id !== null && $scan->transaction_id !== $current?->id)) {
                    $validator->errors()->add('bill_scan_id', 'Ảnh bill không còn hoặc đã gắn vào phiếu khác.');
                }
            }

            // Duyệt giao dịch SePay qua form: chỉ khi tạo phiếu mới, đúng chiều tiền.
            if ($this->filled('sepay_transaction_id')) {
                $sepay = SepayTransaction::find($this->integer('sepay_transaction_id'));
                if ($current !== null || $sepay === null || $sepay->status !== 'pending') {
                    $validator->errors()->add('sepay_transaction_id', 'Giao dịch SePay này không còn chờ duyệt.');
                } elseif (($sepay->transfer_type === 'in') !== ($this->input('type') === 'income')) {
                    $validator->errors()->add('type', $sepay->transfer_type === 'in' ? 'Tiền vào tài khoản thì phải là phiếu thu.' : 'Tiền ra khỏi tài khoản thì phải là phiếu chi.');
                }
            }

            // Duyệt bill thành viên gửi qua form (plan/05 mục 5): chỉ khi tạo phiếu thu mới.
            $claim = null;
            if ($this->filled('payment_claim_id')) {
                $claim = PaymentClaim::find($this->integer('payment_claim_id'));
                if ($current !== null || $claim === null || $claim->status !== 'pending') {
                    $validator->errors()->add('payment_claim_id', 'Bill này không còn chờ duyệt.');
                    $claim = null;
                } elseif ($this->input('type') !== 'income') {
                    $validator->errors()->add('type', 'Bill thành viên gửi thì phải là phiếu thu.');
                }
            }

            // Chống trùng lớp 1: bill trùng mã giao dịch với lần chuyển đã có thì không ra phiếu thứ hai.
            $scan = $claim?->scan ?? ($this->filled('bill_scan_id') ? BillScan::find($this->integer('bill_scan_id')) : null);
            if ($scan !== null && $current === null) {
                $match = app(DuplicateFinder::class)->forScan($scan, $this->filled('sepay_transaction_id') ? $this->integer('sepay_transaction_id') : null);
                if ($match !== null) {
                    $validator->errors()->add($claim ? 'payment_claim_id' : 'bill_scan_id', 'Bill này trùng mã giao dịch với '.PaymentClaimService::matchLabel($match).'. Không tạo phiếu thứ hai cho cùng một lần chuyển.');
                }
            }

            $photos = count($this->file('photos', []));
            if ($current !== null) {
                $kept = TransactionPhoto::where('transaction_id', $current->id)->whereNotIn('id', $this->input('remove_photo_ids', []))->count();
                if ($kept + $photos > config('cashflow.limits.photos_per_transaction')) {
                    $validator->errors()->add('photos', 'Mỗi phiếu tối đa '.config('cashflow.limits.photos_per_transaction').' ảnh.');
                }
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'type' => 'thu hay chi',
            'category_id' => 'loại',
            'amount' => 'số tiền',
            'occurred_at' => 'ngày giờ',
            'title' => 'tiêu đề',
            'description' => 'mô tả',
            'payer_name' => 'tên người',
            'photos' => 'ảnh sự kiện',
            'photos.*' => 'ảnh sự kiện',
            'bill' => 'ảnh bill',
        ];
    }
}
