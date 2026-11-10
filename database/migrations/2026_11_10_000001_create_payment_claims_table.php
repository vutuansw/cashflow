<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thành viên tự gửi bill và chống trùng bill, xem plan/05 mục 5.
 * - bill_scans.file_hash: SHA-256 file ảnh nhận được, bắt gửi lại đúng ảnh cũ.
 * - bill_scans.reference_norm, sepay_transactions.reference_norm: mã giao dịch
 *   ngân hàng đã chuẩn hóa (chữ hoa, chỉ chữ và số) để so trùng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_scans', function (Blueprint $table) {
            $table->char('file_hash', 64)->nullable()->after('path');
            $table->string('reference_norm', 100)->nullable()->after('file_hash');
            $table->index(['group_id', 'file_hash']);
            $table->index(['group_id', 'reference_norm']);
        });

        Schema::table('sepay_transactions', function (Blueprint $table) {
            $table->string('reference_norm', 100)->nullable()->after('reference_code');
            $table->index(['group_id', 'reference_norm']);
        });

        Schema::create('payment_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payer_name', 120);
            $table->unsignedBigInteger('amount');
            $table->foreignId('bill_scan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->enum('status', ['pending', 'approved', 'ignored'])->default('pending');
            $table->string('ignore_reason')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['group_id', 'status', 'created_at']);
            $table->index(['campaign_id', 'contact_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_claims');
        Schema::table('sepay_transactions', function (Blueprint $table) {
            $table->dropIndex(['group_id', 'reference_norm']);
            $table->dropColumn('reference_norm');
        });
        Schema::table('bill_scans', function (Blueprint $table) {
            $table->dropIndex(['group_id', 'file_hash']);
            $table->dropIndex(['group_id', 'reference_norm']);
            $table->dropColumn(['file_hash', 'reference_norm']);
        });
    }
};
