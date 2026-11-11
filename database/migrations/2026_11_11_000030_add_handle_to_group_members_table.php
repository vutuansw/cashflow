<?php

use App\Models\GroupMember;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mã ngẫu nhiên trên link trang thành viên /{ma-nhom}/members/{handle}, thay
 * cho id tự tăng (dò được, lộ quy mô). Xem plan/25.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->string('handle', GroupMember::HANDLE_LENGTH)->nullable()->unique()->after('id');
        });

        DB::table('group_members')->whereNull('handle')->orderBy('id')->pluck('id')->each(
            fn ($id) => DB::table('group_members')->where('id', $id)->update(['handle' => GroupMember::newHandle()])
        );

        Schema::table('group_members', function (Blueprint $table) {
            $table->string('handle', GroupMember::HANDLE_LENGTH)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropUnique(['handle']);
            $table->dropColumn('handle');
        });
    }
};
