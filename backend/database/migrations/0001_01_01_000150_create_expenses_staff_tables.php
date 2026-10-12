<?php

use App\Support\Migration\Columns;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('name');
            Columns::audit($table);

            $table->unique(['society_id', 'name']);
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            Columns::audit($table);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->string('attachment_path')->nullable();
            $table->enum('payment_method', ['cash', 'bank', 'cheque', 'other'])->default('cash');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            Columns::audit($table);

            $table->index(['society_id', 'expense_date']);
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->string('name');
            $table->string('cnic_number', 20);
            $table->string('phone', 20)->nullable();
            $table->string('designation');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->date('joining_date');
            $table->enum('wage_type', ['daily_wage', 'monthly_salary']);
            $table->decimal('rate', 12, 2); // daily rate or monthly salary
            $table->boolean('is_active')->default(true);
            Columns::audit($table);
        });

        Schema::create('staff_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date');
            $table->enum('status', ['present', 'absent', 'half_day', 'leave']);
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['staff_id', 'attendance_date']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            Columns::society($table);
            $table->date('payroll_month');
            $table->enum('status', ['draft', 'finalized'])->default('draft');
            Columns::audit($table);

            $table->unique(['society_id', 'payroll_month']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained()->restrictOnDelete();
            $table->decimal('base_amount', 12, 2);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('advances', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->string('pdf_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('staff_attendance');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('expense_categories');
    }
};
