<?php

use App\Support\Migration\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_alerts', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('raised_by')->constrained('users')->restrictOnDelete();
            $table->enum('type', ['medical', 'fire', 'security', 'other']);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->enum('status', ['pending', 'acknowledged', 'escalated', 'resolved'])->default('pending');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->boolean('escalated')->default(false);
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['society_id', 'status']);
        });

        Schema::create('sos_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sos_alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notified_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('notified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_escalations');
        Schema::dropIfExists('sos_alerts');
    }
};
