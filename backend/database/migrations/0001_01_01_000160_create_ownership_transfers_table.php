<?php

use App\Support\Migration\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ownership_transfers', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->enum('transfer_type', ['sale', 'inheritance', 'gift', 'other']);
            $table->string('previous_owner_name');
            $table->string('previous_owner_cnic', 20)->nullable();
            $table->string('new_owner_name');
            $table->string('new_owner_cnic', 20)->nullable();
            $table->string('new_owner_phone', 20)->nullable();
            $table->date('transfer_date');
            $table->decimal('sale_price', 14, 2)->nullable();
            $table->decimal('transfer_fee', 12, 2)->default(0);
            $table->foreignId('one_off_charge_id')->nullable()->constrained('one_off_charges')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'completed', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            Columns::audit($table);

            $table->index(['society_id', 'unit_id']);
        });

        Schema::create('ownership_transfer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ownership_transfer_id')->constrained()->cascadeOnDelete();
            $table->string('label'); // e.g. Sale Deed, Buyer CNIC, Seller CNIC
            $table->string('file_path');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ownership_transfer_documents');
        Schema::dropIfExists('ownership_transfers');
    }
};
