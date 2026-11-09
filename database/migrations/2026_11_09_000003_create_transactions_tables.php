<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hàng chờ SePay, phiếu thu chi, ảnh sự kiện, nhật ký sửa phiếu, bill chứng từ.
 * Xem plan/01 mục 1, plan/03, plan/04, plan/05.
 *
 * Liên kết phiếu với giao dịch SePay chỉ đi một chiều qua
 * transactions.sepay_transaction_id (unique): một giao dịch sinh tối đa một phiếu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sepay_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sepay_id');
            $table->string('gateway', 60)->nullable();
            $table->dateTime('transaction_date');
            $table->string('account_number', 40)->nullable();
            $table->boolean('account_mismatch')->default(false);
            $table->enum('transfer_type', ['in', 'out']);
            $table->unsignedBigInteger('amount');
            $table->text('content')->nullable();
            $table->string('reference_code', 100)->nullable();
            $table->json('raw');
            $table->foreignId('matched_campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignId('suggested_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'ignored'])->default('pending');
            $table->string('ignore_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'sepay_id']);
            $table->index(['group_id', 'status', 'transaction_date']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->dateTime('occurred_at');
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('payer_name', 120)->nullable();
            $table->enum('source', ['manual', 'ai_bill', 'sepay'])->default('manual');
            $table->foreignId('sepay_transaction_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->boolean('show_on_timeline')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['group_id', 'occurred_at']);
            $table->index(['group_id', 'type', 'category_id']);
        });

        Schema::create('transaction_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('thumb_path');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('transaction_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('action', ['created', 'updated', 'deleted', 'restored']);
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('bill_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path')->nullable();
            $table->enum('status', ['processing', 'done', 'failed'])->default('processing');
            $table->string('model', 100)->nullable();
            $table->boolean('fallback_used')->default(false);
            $table->json('result')->nullable();
            $table->json('draft')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_scans');
        Schema::dropIfExists('transaction_histories');
        Schema::dropIfExists('transaction_photos');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('sepay_transactions');
    }
};
