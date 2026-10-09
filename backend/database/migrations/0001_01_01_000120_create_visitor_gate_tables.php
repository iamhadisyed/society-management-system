<?php

use App\Support\Migration\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_passes', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_resident')->constrained('users')->cascadeOnDelete();
            $table->string('guest_name');
            $table->string('vehicle_number')->nullable();
            $table->dateTime('window_start');
            $table->dateTime('window_end');
            $table->string('code')->unique(); // QR-encoded entry code
            $table->enum('type', ['pre_approved', 'unexpected', 'delivery', 'ride_hailing'])->default('pre_approved');
            $table->enum('status', ['pending_approval', 'approved', 'denied', 'used', 'expired'])->default('approved');
            Columns::audit($table);
        });

        Schema::create('gate_logs', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('visitor_pass_id')->nullable()->constrained('visitor_passes')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('visitor_name')->nullable();
            $table->string('vehicle_number')->nullable();
            $table->enum('entry_type', ['guest', 'delivery', 'ride_hailing', 'domestic_staff', 'other'])->default('guest');
            $table->foreignId('logged_by')->constrained('users')->restrictOnDelete(); // guard
            $table->dateTime('entry_time');
            $table->dateTime('exit_time')->nullable();
            $table->timestamps();

            $table->index(['society_id', 'entry_time']);
        });

        Schema::create('domestic_staff', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('cnic_number', 20);
            $table->string('photo_path')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('role')->nullable(); // maid, driver, cook, gardener
            $table->boolean('is_active')->default(true);
            Columns::audit($table);
        });

        Schema::create('domestic_staff_logs', function (Blueprint $table) {
            $table->id();
            // Table is `domestic_staff` (singular - "staff" doesn't pluralize
            // normally), but constrained() without an argument guesses
            // `domestic_staffs` from the column name - must be explicit.
            $table->foreignId('domestic_staff_id')->constrained('domestic_staff')->cascadeOnDelete();
            $table->foreignId('logged_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('entry_time');
            $table->dateTime('exit_time')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('registration_number');
            $table->string('make')->nullable();
            $table->string('color')->nullable();
            $table->string('sticker_number')->nullable();
            Columns::audit($table);

            $table->unique(['society_id', 'registration_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('domestic_staff_logs');
        Schema::dropIfExists('domestic_staff');
        Schema::dropIfExists('gate_logs');
        Schema::dropIfExists('visitor_passes');
    }
};
