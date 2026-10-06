<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 — Pharmacist + Store (PLAN.md section 10).
 *
 * Turns the legacy three-field medicines table into a proper pharmacy:
 *   - the master grows generic, composition, manufacturer, supplier, mfg/expiry
 *     and a reorder level, so the store can warn before stock runs out;
 *   - a stock ledger records every movement (purchase, dispense, return, dead
 *     stock), so stock level is provable and not just a number typed in;
 *   - a prescription links its items to the medicine master, which is what
 *     lets "dispense" deduct the right stock; prescriptions carry a payment
 *     state so a handed-over medicine can post to the patient's bill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $blueprint) {
            $blueprint->string('generic', 150)->nullable()->after('name');
            $blueprint->string('composition', 255)->nullable()->after('generic');
            $blueprint->string('supplier', 150)->nullable()->after('manufacturer');
            $blueprint->date('mfg_date')->nullable()->after('supplier');
            $blueprint->decimal('mrp', 10, 2)->nullable()->after('price');
            $blueprint->unsignedInteger('reorder_level')->nullable()->after('stock');
        });

        Schema::table('prescription_items', function (Blueprint $blueprint) {
            $blueprint->unsignedBigInteger('medicine_id')->nullable()->after('medicine');
            $blueprint->unsignedInteger('dispensed_qty')->default(0)->after('medicine_id');
            $blueprint->timestamp('dispensed_at')->nullable()->after('dispensed_qty');
            $blueprint->unsignedBigInteger('dispensed_by')->nullable()->after('dispensed_at');

            $blueprint->foreign('medicine_id')->references('id')->on('medicines')->nullOnDelete();
            $blueprint->foreign('dispensed_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('prescriptions', function (Blueprint $blueprint) {
            $blueprint->timestamp('dispensed_at')->nullable()->after('issued_at');
            $blueprint->unsignedBigInteger('dispensed_by')->nullable()->after('dispensed_at');
            // paid once the pharmacist hands the medicines over and posts them.
            $blueprint->enum('payment_status', ['unpaid', 'paid'])->default('unpaid')->after('dispensed_by');

            $blueprint->foreign('dispensed_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('stock_movements', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->unsignedBigInteger('tenant_id')->nullable();
            $blueprint->unsignedBigInteger('medicine_id');
            // signed: purchase (+) and return (+) add, dispense/expiry (-) take.
            $blueprint->integer('quantity');
            $blueprint->enum('type', ['purchase', 'dispense', 'return', 'adjustment', 'expiry'])->default('purchase');
            $blueprint->string('batch_no', 60)->nullable();
            $blueprint->string('reference', 120)->nullable();
            $blueprint->unsignedBigInteger('user_id')->nullable();
            $blueprint->string('note', 255)->nullable();
            $blueprint->timestamps();

            $blueprint->index(['tenant_id']);
            $blueprint->index(['medicine_id', 'created_at']);
            $blueprint->foreign('medicine_id')->references('id')->on('medicines')->cascadeOnDelete();
            $blueprint->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');

        Schema::table('prescriptions', function (Blueprint $blueprint) {
            $blueprint->dropForeign(['dispensed_by']);
            $blueprint->dropColumn(['dispensed_at', 'dispensed_by', 'payment_status']);
        });

        Schema::table('prescription_items', function (Blueprint $blueprint) {
            $blueprint->dropForeign(['medicine_id']);
            $blueprint->dropForeign(['dispensed_by']);
            $blueprint->dropColumn(['medicine_id', 'dispensed_qty', 'dispensed_at', 'dispensed_by']);
        });

        Schema::table('medicines', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['generic', 'composition', 'supplier', 'mfg_date', 'mrp', 'reorder_level']);
        });
    }
};