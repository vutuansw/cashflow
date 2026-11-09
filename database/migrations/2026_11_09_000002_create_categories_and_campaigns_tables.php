<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loại thu chi và chiến dịch thu. Xem plan/01 mục 1, plan/05.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['income', 'expense']);
            $table->string('name', 80);
            $table->string('icon', 40)->nullable();
            $table->string('color', 9)->nullable();
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'type', 'name']);
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('slug', 80);
            // Mã nội dung chuyển khoản: chữ in hoa và số, duy nhất toàn hệ thống.
            $table->string('transfer_code', 12)->unique();
            $table->unsignedBigInteger('amount_per_member')->nullable();
            $table->unsignedBigInteger('target_amount')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'slug']);
            $table->index(['group_id', 'status']);
        });

        Schema::create('campaign_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('expected_amount')->nullable();
            $table->boolean('exempt')->default(false);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_members');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('categories');
    }
};
