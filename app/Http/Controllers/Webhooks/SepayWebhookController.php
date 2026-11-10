<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\SendSepayDigest;
use App\Models\Campaign;
use App\Models\Group;
use App\Models\SepayTransaction;
use App\Services\GroupServiceStatus;
use App\Services\Sepay\SepayMatcher;
use App\Support\CurrentGroup;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

/**
 * POST /api/webhooks/sepay/{ma-nhom}: SePay báo biến động số dư. Lưu vào hàng
 * chờ duyệt, không tạo phiếu. Trả {"success": true} thì SePay mới coi là đã
 * nhận, nếu không sẽ gửi lại. Xem plan/05 mục 3.
 */
class SepayWebhookController extends Controller
{
    public function __invoke(Request $request, string $group, GroupServiceStatus $status, CurrentGroup $current, SepayMatcher $matcher)
    {
        $model = Group::query()->where('code', strtolower($group))->first();
        if ($model === null) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy nhóm.'], 404);
        }
        if ($status->isLocked($model)) {
            return response()->json(['success' => false, 'message' => 'Nhóm đang tạm khóa.'], 423);
        }

        // Authorization: Apikey {key}, so sánh thời gian hằng.
        $key = (string) $model->sepay_api_key;
        $sent = trim((string) preg_replace('/^Apikey\s+/i', '', (string) $request->header('Authorization')));
        if ($key === '' || $sent === '' || ! hash_equals($key, $sent)) {
            return response()->json(['success' => false, 'message' => 'Sai API key.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'min:1'],
            'transferType' => ['required', 'in:in,out'],
            'transferAmount' => ['required', 'numeric', 'min:1'],
            'transactionDate' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $current->set($model);
        $data = $request->all();

        // SePay gửi lại khi lần trước không nhận được phản hồi: đã có thì thôi.
        if (SepayTransaction::query()->where('sepay_id', (int) $data['id'])->exists()) {
            return response()->json(['success' => true]);
        }

        try {
            $at = Carbon::parse($data['transactionDate'], config('app.timezone'));
        } catch (\Throwable) {
            $at = now();
        }
        $content = isset($data['content']) ? mb_substr((string) $data['content'], 0, 1000) : null;
        $amount = (int) round((float) $data['transferAmount']);
        $draft = $matcher->draft($data['transferType'], $amount, $at->format('Y-m-d H:i'), $content, $data['description'] ?? null);

        // Tiền vào không có mã chiến dịch (khách quét QR không nội dung, plan/05 mục 1):
        // nhóm chỉ có một chiến dịch đang mở thì đoán là chiến dịch đó, QTV vẫn duyệt.
        $campaignId = $data['transferType'] === 'in' ? $draft['campaign_id'] : null;
        $guessed = false;
        if ($data['transferType'] === 'in' && $campaignId === null) {
            $open = Campaign::query()->where('status', 'open')->limit(2)->pluck('id');
            if ($open->count() === 1) {
                $campaignId = $open->first();
                $guessed = true;
            }
        }

        $account = preg_replace('/\D/', '', (string) ($data['accountNumber'] ?? ''));
        $ours = preg_replace('/\D/', '', (string) $model->bank_account_no);

        try {
            SepayTransaction::forceCreate([
                'group_id' => $model->id,
                'sepay_id' => (int) $data['id'],
                'gateway' => isset($data['gateway']) ? mb_substr((string) $data['gateway'], 0, 60) : null,
                'transaction_date' => $at,
                'account_number' => $account !== '' ? mb_substr($account, 0, 40) : null,
                'account_mismatch' => $account !== '' && $ours !== '' && $account !== $ours,
                'transfer_type' => $data['transferType'],
                'amount' => $amount,
                'content' => $content,
                'reference_code' => isset($data['referenceCode']) ? mb_substr((string) $data['referenceCode'], 0, 100) : null,
                'raw' => $data,
                'matched_campaign_id' => $campaignId,
                'campaign_guessed' => $guessed,
                'suggested_contact_id' => $draft['contact_id'],
                'status' => 'pending',
            ]);
        } catch (UniqueConstraintViolationException) {
            // Hai lần gửi trùng tới cùng lúc.
            return response()->json(['success' => true]);
        }

        $model->forceFill(['sepay_last_hook_at' => now()])->saveQuietly();

        // Email gộp: tối đa một lượt mỗi 15 phút mỗi nhóm (plan/03 mục 8).
        if (Cache::add("sepay-digest:{$model->id}", true, now()->addMinutes(15))) {
            SendSepayDigest::dispatch($model->id)->delay(now()->addMinute());
        }

        return response()->json(['success' => true]);
    }
}
