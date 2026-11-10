<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link đăng ký vào nhóm /{ma-nhom}/register: người đăng ký nằm ở bảng riêng
 * cho tới khi QTV duyệt, không đụng danh bạ hay group_members. Xem plan/13.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('join_link_enabled')->default(true)->after('visibility');
        });

        Schema::create('group_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            // Một dòng mỗi người mỗi nhóm; xin lại sau khi bị từ chối thì dùng lại dòng cũ.
            $table->unique(['group_id', 'user_id']);
            $table->index(['group_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_join_requests');
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('join_link_enabled');
        });
    }
};
