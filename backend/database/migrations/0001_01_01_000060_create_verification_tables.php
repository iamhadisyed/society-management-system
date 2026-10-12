<?php

use App\Support\Migration\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_attempts', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('device_id')->nullable();
            $table->string('ip_address', 45);
            $table->string('submitted_name')->nullable();
            $table->string('submitted_bill_number')->nullable();
            $table->boolean('matched')->default(false);
            $table->timestamps();

            $table->index(['device_id', 'unit_id']);
            $table->index(['ip_address']);
        });

        // Raised when a unit already has a linked user and a new verification attempt matches the same data
        Schema::create('unit_claim_disputes', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('existing_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('claimant_name');
            $table->string('claimant_bill_number');
            $table->string('claimant_password_hash'); // held pending resolution, not an active login
            $table->enum('status', ['pending', 'approved_new_owner', 'rejected'])->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });

        // Registrations that didn't auto-match (name/bill number mismatch) but weren't rejected outright
        Schema::create('pending_verifications', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('submitted_name');
            $table->string('submitted_bill_number');
            $table->string('password_hash');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_verifications');
        Schema::dropIfExists('unit_claim_disputes');
        Schema::dropIfExists('verification_attempts');
    }
};
