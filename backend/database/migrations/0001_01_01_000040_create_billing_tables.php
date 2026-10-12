<?php

use App\Support\Migration\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_heads', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('name'); // e.g. Water, Sewerage, Security & Maintenance
            $table->enum('frequency', ['monthly', 'quarterly', 'yearly', 'one_time']);
            $table->boolean('is_active')->default(true);
            Columns::audit($table);

            $table->unique(['society_id', 'name']);
        });

        // Unit Category x Tariff Type x Charge Head -> Amount, versioned by effective_from
        Schema::create('rate_matrix', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('unit_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tariff_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_head_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable(); // null = currently active
            Columns::audit($table);

            $table->index(['society_id', 'unit_category_id', 'tariff_type_id', 'charge_head_id', 'effective_from'], 'rate_matrix_lookup_idx');
        });

        // Unit-level extra charge or waiver/discount on a charge head
        Schema::create('unit_charge_overrides', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_head_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['extra', 'waiver']);
            $table->enum('value_type', ['fixed', 'percent']);
            $table->decimal('value', 12, 2);
            $table->text('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            Columns::audit($table);
        });

        Schema::create('bill_runs', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->date('billing_month'); // first of month
            $table->enum('status', ['draft', 'generated', 'locked'])->default('draft');
            $table->text('announcement')->nullable(); // Urdu/English, printed on bill
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            Columns::audit($table);

            $table->unique(['society_id', 'billing_month']);
        });

        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('bill_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('bill_number'); // {society_code}-{YYYYMM}-{unit_seq}
            $table->string('reference_number'); // snapshot of unit's permanent reference number
            $table->date('billing_month');
            $table->date('issued_on');
            $table->date('due_date');
            $table->decimal('arrears', 12, 2)->default(0);
            $table->decimal('this_month_total', 12, 2)->default(0);
            $table->decimal('adjustments_total', 12, 2)->default(0);
            $table->decimal('surcharge_amount', 12, 2)->default(0);
            $table->decimal('payable_within_due', 12, 2)->default(0);
            $table->decimal('payable_after_due', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->enum('status', ['unpaid', 'partially_paid', 'paid', 'overdue'])->default('unpaid');
            $table->boolean('app_linked_snapshot')->default(false);
            $table->date('app_linked_since_snapshot')->nullable();
            // resident/address snapshot fields so historic bills never change even if the unit record is later edited
            $table->string('owner_name_snapshot')->nullable();
            $table->string('occupant_name_snapshot')->nullable();
            $table->text('address_snapshot')->nullable();
            $table->string('residence_status_snapshot')->nullable();
            $table->string('tariff_type_snapshot')->nullable();
            Columns::audit($table);

            $table->unique(['society_id', 'bill_number']);
            $table->index(['unit_id', 'billing_month']);
            $table->index('status');
        });

        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_head_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('serial');
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        // Credit/debit adjustment lines (can be negative), audited
        Schema::create('bill_adjustments', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 12, 2);
            $table->text('reason');
            Columns::audit($table);
        });

        // One-off charges added to specific units (form fee, facility booking fee, fine, ...)
        Schema::create('one_off_charges', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->text('reason')->nullable();
            $table->string('source_type')->nullable(); // e.g. FormSubmission, FacilityBooking, WaterTankerRequest
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('applied_bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->enum('status', ['pending', 'applied'])->default('pending');
            Columns::audit($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('one_off_charges');
        Schema::dropIfExists('bill_adjustments');
        Schema::dropIfExists('bill_items');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('bill_runs');
        Schema::dropIfExists('unit_charge_overrides');
        Schema::dropIfExists('rate_matrix');
        Schema::dropIfExists('charge_heads');
    }
};
