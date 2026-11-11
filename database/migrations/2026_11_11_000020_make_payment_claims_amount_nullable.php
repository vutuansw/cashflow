<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bill có ảnh không cần người gửi khai số tiền, AI đọc từ ảnh (plan/23).
 * amount null = người gửi không khai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_claims', function (Blueprint $table) {
            $table->unsignedBigInteger('amount')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_claims', function (Blueprint $table) {
            $table->unsignedBigInteger('amount')->nullable(false)->default(0)->change();
        });
    }
};
