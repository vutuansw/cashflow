<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nội quy nhóm do trưởng nhóm/QTV viết, hiện ở box "Nội quy" trang nhóm. Xem plan/15. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->text('rules')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('rules');
        });
    }
};
