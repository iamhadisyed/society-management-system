<?php

use App\Support\Migration\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_templates', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('name'); // e.g. NOC for Sale/Transfer
            $table->text('instructions')->nullable();
            $table->decimal('fee', 12, 2)->default(0);
            $table->enum('fee_payment_mode', ['add_to_bill', 'pay_now', 'none'])->default('none');
            $table->boolean('requires_dues_clearance')->default(false); // e.g. NOC
            $table->boolean('generates_pdf')->default(false);
            $table->boolean('is_active')->default(true);
            Columns::audit($table);

            $table->unique(['society_id', 'name']);
        });

        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_template_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('key'); // slug used as the value storage key
            $table->enum('type', ['text', 'number', 'date', 'dropdown', 'checkbox', 'file']);
            $table->json('options')->nullable(); // for dropdown
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['form_template_id', 'key']);
        });

        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('form_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['open', 'in_process', 'accepted', 'rejected'])->default('open');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('generated_pdf_path')->nullable();
            Columns::audit($table);

            $table->index(['society_id', 'status']);
        });

        Schema::create('form_submission_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_field_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });

        Schema::create('form_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('comment');
            $table->timestamps();
        });

        Schema::create('form_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('file_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_attachments');
        Schema::dropIfExists('form_comments');
        Schema::dropIfExists('form_submission_values');
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('form_templates');
    }
};
