<?php

use App\Support\Migration\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('societies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique(); // used by residents to find the society
            $table->string('logo_path')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_iban')->nullable();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->string('timezone')->default('Asia/Karachi');
            $table->string('default_language', 5)->default('en');
            $table->json('settings')->nullable(); // sos_escalation_seconds, verification_lockout_attempts, blood_donor_cooldown_days, complaint_reopen_days, etc.
            $table->foreignId('created_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Now that `societies` exists, wire up the FK on `users.society_id`
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('society_id')->references('id')->on('societies')->cascadeOnDelete();
        });

        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('society_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->decimal('price_at_signup', 12, 2);
            $table->timestamps();
        });

        Schema::create('platform_audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_admin_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->string('action'); // e.g. society.created, society.suspended
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_log');
        Schema::dropIfExists('society_subscriptions');
        Schema::dropIfExists('subscription_plans');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['society_id']);
        });
        Schema::dropIfExists('societies');
        Schema::dropIfExists('platform_admins');
    }
};
