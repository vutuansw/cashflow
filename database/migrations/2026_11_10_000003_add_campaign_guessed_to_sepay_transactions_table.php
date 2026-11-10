<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiền vào không có mã chiến dịch (khách quét QR không nội dung) mà nhóm chỉ có
 * một chiến dịch đang mở: gợi ý chiến dịch đó và đánh dấu là đoán (plan/05 mục 3 bước 6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sepay_transactions', function (Blueprint $table) {
            $table->boolean('campaign_guessed')->default(false)->after('matched_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::table('sepay_transactions', function (Blueprint $table) {
            $table->dropColumn('campaign_guessed');
        });
    }
};
