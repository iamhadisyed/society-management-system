<?php

use App\Support\Migration\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blood_donors', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // account owner (unit)
            $table->string('name');
            $table->enum('blood_group', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']);
            $table->string('phone', 20);
            $table->boolean('is_available')->default(true);
            $table->date('last_donation_date')->nullable();
            Columns::audit($table);

            $table->index(['society_id', 'blood_group', 'is_available']);
        });

        Schema::create('blood_requests', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('target_donor_id')->nullable()->constrained('blood_donors')->nullOnDelete(); // set if requested from a specific donor card
            $table->enum('blood_group', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']);
            $table->unsignedInteger('units_needed')->default(1);
            $table->string('hospital');
            $table->enum('urgency', ['normal', 'urgent'])->default('normal');
            $table->date('needed_by');
            $table->enum('status', ['open', 'fulfilled', 'expired', 'cancelled'])->default('open');
            $table->timestamps();
        });

        Schema::create('blood_request_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blood_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('blood_donor_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['offered', 'contact_shared', 'declined'])->default('offered');
            $table->timestamps();

            $table->unique(['blood_request_id', 'blood_donor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blood_request_responses');
        Schema::dropIfExists('blood_requests');
        Schema::dropIfExists('blood_donors');
    }
};
