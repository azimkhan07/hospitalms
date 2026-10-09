<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Itemised billing: every charge becomes its own auditable row so the
        // bill total is derived instead of typed in (PLAN billing milestone).
        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('bill_id')->constrained('bills')->cascadeOnDelete();
            $table->index('bill_id');
            $table->string('description');
            $table->string('category', 30)->nullable();
            $table->decimal('qty', 8, 2)->default(1);
            $table->decimal('rate', 10, 2)->default(0);
            $table->decimal('amount', 10, 2)->default(0); // qty * rate, frozen for audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->foreignId('stay_id')->nullable()->after('patients_id')->constrained('stays')->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0)->after('discount');
            $table->decimal('advance_used', 10, 2)->default(0)->after('discount_amount');
            $table->string('remarks')->nullable()->after('advance_used');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stay_id');
            $table->dropColumn(['discount_amount', 'advance_used', 'remarks']);
        });

        Schema::dropIfExists('bill_items');
    }
};
