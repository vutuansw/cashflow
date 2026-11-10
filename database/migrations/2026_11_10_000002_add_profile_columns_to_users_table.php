<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trang cá nhân kiểu Facebook, xem plan/08 mục 8: ảnh bìa và lời giới thiệu
 * ngắn. Ảnh đại diện dùng cột avatar_path đã có.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('avatar_path');
            $table->string('bio', 160)->nullable()->after('cover_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['cover_path', 'bio']);
        });
    }
};
