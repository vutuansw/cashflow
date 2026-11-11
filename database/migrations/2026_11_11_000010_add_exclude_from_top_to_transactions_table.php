<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phiếu thu QTV ẩn khỏi box "Top đóng góp" (vd đóng bù năm trước). Xem plan/22. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('exclude_from_top')->default(false)->after('show_on_timeline');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('exclude_from_top');
        });
    }
};
