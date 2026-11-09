<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Database cashflow_system: quản trị viên HIPOTECH, trạng thái dịch vụ của
 * từng nhóm, nhật ký quản trị, thiết lập nền tảng. Xem plan/01 mục 2.
 *
 * group_id trỏ sang cashflow_groups.groups.id bằng quy ước, không có khóa
 * ngoại vì khác database.
 */
return new class extends Migration
{
    protected $connection = 'system';

    public function up(): void
    {
        Schema::connection('system')->create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 190)->unique();
            $table->string('password');
            $table->enum('status', ['active', 'locked'])->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('system')->create('group_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->unique();
            $table->enum('review_status', ['pending_review', 'approved', 'rejected'])->default('pending_review');
            $table->enum('service_status', ['active', 'locked'])->default('active');
            $table->string('lock_reason')->nullable();
            // null = không giới hạn (mặc định), xem plan/04 mục 6.
            $table->unsignedInteger('ai_monthly_quota')->nullable();
            $table->unsignedInteger('max_photos_mb')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['review_status', 'service_status']);
        });

        Schema::connection('system')->create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('action', 40);
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::connection('system')->create('platform_settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('system')->dropIfExists('platform_settings');
        Schema::connection('system')->dropIfExists('admin_audit_logs');
        Schema::connection('system')->dropIfExists('group_services');
        Schema::connection('system')->dropIfExists('admins');
    }
};
