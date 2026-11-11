<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bài đăng của thành viên (chữ + ảnh), không qua phiếu thu chi. Tim và bình
 * luận dùng chung bảng cũ: mỗi dòng có đúng một trong transaction_id hoặc
 * status_post_id (kiểm trong code). Chế độ đăng bài của nhóm nằm ở
 * groups.post_mode. Xem plan/21.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->enum('post_mode', ['open', 'review', 'off'])->default('open')->after('join_link_enabled');
        });

        Schema::create('status_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->enum('status', ['published', 'pending', 'rejected']);
            $table->dateTime('published_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reject_reason', 300)->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['group_id', 'status', 'published_at']);
            $table->index(['group_id', 'user_id', 'published_at']);
        });

        Schema::create('status_post_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_post_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('thumb_path');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        foreach (['reactions', 'comments'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('transaction_id')->nullable()->change();
                $table->foreignId('status_post_id')->nullable()->after('transaction_id')->constrained()->cascadeOnDelete();
            });
        }

        Schema::table('reactions', function (Blueprint $table) {
            $table->unique(['status_post_id', 'user_id']);
        });
        Schema::table('comments', function (Blueprint $table) {
            $table->index(['status_post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex(['status_post_id', 'created_at']);
        });
        Schema::table('reactions', function (Blueprint $table) {
            $table->dropUnique(['status_post_id', 'user_id']);
        });
        foreach (['reactions', 'comments'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('status_post_id');
            });
        }

        Schema::dropIfExists('status_post_photos');
        Schema::dropIfExists('status_posts');

        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('post_mode');
        });
    }
};
