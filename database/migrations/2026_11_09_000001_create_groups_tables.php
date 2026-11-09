<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhóm, danh bạ thành viên và tài khoản thuộc nhóm. Xem plan/01 mục 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->enum('visibility', ['public', 'private'])->default('public');
            $table->foreignId('owner_id')->constrained('users');
            $table->unsignedBigInteger('opening_balance')->default(0);
            $table->date('opening_balance_date')->nullable();
            $table->string('bank_code', 30)->nullable();
            $table->text('bank_account_no')->nullable();
            $table->string('bank_account_name', 120)->nullable();
            $table->text('sepay_api_key')->nullable();
            $table->timestamp('sepay_last_hook_at')->nullable();
            $table->json('notify_emails')->nullable();
            $table->json('notify_settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('full_name', 120);
            $table->string('search_name', 120);
            $table->text('phone')->nullable();
            $table->char('phone_hash', 64)->nullable();
            $table->date('birthday')->nullable();
            $table->text('address')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['active', 'left'])->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['group_id', 'phone_hash']);
            $table->index(['group_id', 'status', 'search_name']);
        });

        Schema::create('group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'admin', 'member'])->default('member');
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['active', 'removed'])->default('active');
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('groups');
    }
};
