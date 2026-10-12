<?php

use App\Support\Migration\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('name'); // e.g. Maintenance, Electrical, Sanitation, Security
            $table->foreignId('department_head_id')->nullable()->constrained('users')->nullOnDelete();
            Columns::audit($table);

            $table->unique(['society_id', 'name']);
        });

        Schema::create('department_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['department_id', 'user_id']);
        });

        Schema::create('complaint_categories', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('name'); // e.g. Plumbing, Street Light, Garbage, Security
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            Columns::audit($table);

            $table->unique(['society_id', 'name']);
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('ticket_number')->unique();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('raised_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('complaint_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->timestamp('preferred_time')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['open', 'assigned', 'in_progress', 'on_hold', 'resolved', 'closed', 'reopened', 'dropped'])->default('open');
            $table->text('drop_reason')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->boolean('sla_breached')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedTinyInteger('rating')->nullable(); // 1-5
            $table->text('rating_comment')->nullable();
            Columns::audit($table);

            $table->index(['society_id', 'status']);
            $table->index('department_id');
        });

        Schema::create('complaint_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('complaint_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('comment');
            $table->timestamps();
        });

        Schema::create('complaint_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('file_path');
            $table->enum('type', ['before', 'after', 'other'])->default('other');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_attachments');
        Schema::dropIfExists('complaint_comments');
        Schema::dropIfExists('complaint_status_history');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('complaint_categories');
        Schema::dropIfExists('department_agents');
        Schema::dropIfExists('departments');
    }
};
