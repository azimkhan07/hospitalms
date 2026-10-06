<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // GST-ready invoices: a bill now carries its own money, not a guess
        // from the payments table. Pharmacist handover and accountant edits
        // both write here; payments only ever reduce the balance.
        Schema::table('bills', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->default(0)->after('status');
            $table->decimal('tax', 12, 2)->default(0)->after('amount');
            $table->decimal('discount', 12, 2)->default(0)->after('tax');
            $table->string('invoice_no', 40)->nullable()->after('discount');
            $table->timestamp('paid_at')->nullable()->after('invoice_no');
            $table->foreignId('issued_by')->nullable()->constrained('users')->after('paid_at');
            $table->index(['status', 'paid_at']);
        });

        // Salaries are generated per employee per month as a due voucher and
        // paid in one click; the payment becomes an expense on the ledger.
        Schema::create('salary_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('employee_id')->constrained('employees');
            $table->decimal('gross', 12, 2)->default(0);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net', 12, 2)->default(0);
            $table->string('month', 7); // Y-m
            $table->enum('status', ['due', 'paid'])->default('due');
            $table->timestamp('paid_at')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['tenant_id', 'employee_id', 'month']);
        });

        // The accountant's ledger: every money movement the panel makes, in
        // date order. Income vouchers come from patient payments, expense
        // vouchers from salary payments (and any manual adjustment).
        Schema::create('accounting_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->enum('type', ['income', 'expense'])->index();
            $table->string('title', 160);
            $table->string('note', 255)->nullable();
            $table->decimal('amount', 12, 2)->default(0); // always positive
            $table->string('ref_type', 30)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['ref_type', 'ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_vouchers');
        Schema::dropIfExists('salary_vouchers');

        Schema::table('bills', function (Blueprint $table) {
            $table->dropForeign(['issued_by']);
            $table->dropColumn(['amount', 'tax', 'discount', 'invoice_no', 'paid_at', 'issued_by']);
        });
    }
};