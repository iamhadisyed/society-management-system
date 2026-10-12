<?php

use App\Support\Migration\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->enum('method', ['cash', 'bank', 'jazzcash', 'easypaisa', 'raast', 'other']);
            $table->decimal('amount', 12, 2);
            $table->date('paid_on');
            $table->string('bank_reference')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            Columns::audit($table);

            $table->index(['society_id', 'unit_id']);
        });

        // A payment can settle one or more bills (partial payments supported)
        Schema::create('payment_bill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_applied', 12, 2);
            $table->timestamps();
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_number');
            $table->timestamp('issued_at');
            Columns::audit($table);

            $table->unique(['society_id', 'receipt_number']);
        });

        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('paid_on');
            $table->string('transaction_id')->nullable();
            $table->string('proof_file_path'); // private disk
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('resulting_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_statement_imports', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('file_path');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('matched_rows')->default(0);
            $table->unsignedInteger('unmatched_rows')->default(0);
            $table->unsignedInteger('conflicting_rows')->default(0);
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            Columns::audit($table);
        });

        Schema::create('bank_reconciliation_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_import_id')->constrained()->cascadeOnDelete();
            $table->date('transaction_date');
            $table->string('description')->nullable();
            $table->string('reference_extracted')->nullable();
            $table->decimal('amount', 12, 2);
            $table->foreignId('matched_bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->foreignId('resulting_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->enum('status', ['matched', 'unmatched', 'conflicting', 'resolved'])->default('unmatched');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Abstraction layer for online payment gateways (JazzCash/Easypaisa/Raast/...)
        Schema::create('payment_gateway_transactions', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('bill_id')->constrained()->restrictOnDelete();
            $table->string('gateway'); // jazzcash | easypaisa | raast
            $table->string('gateway_transaction_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['initiated', 'success', 'failed', 'cancelled'])->default('initiated');
            $table->json('gateway_response')->nullable();
            $table->foreignId('resulting_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_transactions');
        Schema::dropIfExists('bank_reconciliation_rows');
        Schema::dropIfExists('bank_statement_imports');
        Schema::dropIfExists('payment_proofs');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('payment_bill');
        Schema::dropIfExists('payments');
    }
};
