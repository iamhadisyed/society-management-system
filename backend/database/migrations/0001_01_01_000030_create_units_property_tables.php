<?php

use App\Support\Migration\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_categories', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('name'); // e.g. 5 Marla, 1 Kanal, 2-Bed Flat
            $table->text('description')->nullable();
            Columns::audit($table);

            $table->unique(['society_id', 'name']);
        });

        Schema::create('tariff_types', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('name'); // e.g. Domestic, Commercial
            Columns::audit($table);

            $table->unique(['society_id', 'name']);
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('name'); // e.g. E Block
            Columns::audit($table);

            $table->unique(['society_id', 'name']);
        });

        Schema::create('streets', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            Columns::audit($table);

            $table->unique(['block_id', 'name']);
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('block_id')->constrained()->restrictOnDelete();
            $table->foreignId('street_id')->constrained()->restrictOnDelete();
            $table->string('unit_number');
            $table->text('full_address');
            $table->foreignId('unit_category_id')->constrained('unit_categories')->restrictOnDelete();
            $table->foreignId('tariff_type_id')->constrained('tariff_types')->restrictOnDelete();
            $table->enum('residence_status', ['owner', 'tenant'])->default('owner');
            $table->string('owner_name')->nullable();
            $table->string('occupant_name')->nullable();
            $table->string('reference_number')->unique(); // permanent, assigned once, never reused
            $table->string('current_bill_number')->nullable(); // denormalized latest bill number, used by verification flow
            $table->foreignId('linked_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('linked_at')->nullable();
            $table->boolean('is_active')->default(true);
            Columns::audit($table);

            $table->unique(['society_id', 'block_id', 'street_id', 'unit_number']);
            $table->index('linked_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
        Schema::dropIfExists('streets');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('tariff_types');
        Schema::dropIfExists('unit_categories');
    }
};
