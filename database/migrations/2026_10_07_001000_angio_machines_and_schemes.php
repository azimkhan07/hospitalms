<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Angiography (cath lab) machines and Government schemes (yojna).
 *
 * A facility that owns an angiography machine registers it here -- only then
 * can a patient's treatment be marked as "done on angio". Similarly a patient
 * may be enrolled in a Government scheme so the amount comes from the state
 * and the accounting ledger can book it as income (via Accounting::bookIncome).
 *
 * Both are tenant-owned masters; appointments carry optional foreign keys to
 * keep the audit trail of "how was this treatment done".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('angio_machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 40)->nullable();
            $table->string('manufacturer', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('serial_number', 120)->nullable();
            $table->string('vendor', 150)->nullable();
            $table->string('location', 150)->nullable();
            $table->date('installed_at')->nullable();
            $table->string('status', 30)->default('working');
            $table->decimal('rate', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 40)->nullable();
            $table->string('provider', 150)->nullable();
            $table->enum('coverage_type', ['percent', 'amount'])->default('amount');
            $table->decimal('coverage_value', 12, 2)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'is_active']);
        });

        if (Schema::hasTable('patients') && ! Schema::hasColumn('patients', 'scheme_id')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->unsignedBigInteger('scheme_id')->nullable()->after('status');
                $table->foreign('scheme_id')->references('id')->on('schemes')->nullOnDelete();
            });
        }

        if (Schema::hasTable('appointments') && ! Schema::hasColumn('appointments', 'scheme_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->unsignedBigInteger('angio_machine_id')->nullable()->after('doctor_id');
                $table->unsignedBigInteger('scheme_id')->nullable()->after('angio_machine_id');
                $table->foreign('angio_machine_id')->references('id')->on('angio_machines')->nullOnDelete();
                $table->foreign('scheme_id')->references('id')->on('schemes')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('appointments') && Schema::hasColumn('appointments', 'scheme_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropForeign(['scheme_id']);
                $table->dropForeign(['angio_machine_id']);
                $table->dropColumn(['scheme_id', 'angio_machine_id']);
            });
        }

        if (Schema::hasTable('patients') && Schema::hasColumn('patients', 'scheme_id')) {
            Schema::table('patients', function (Blueprint $table) {
                $table->dropForeign(['scheme_id']);
                $table->dropColumn('scheme_id');
            });
        }

        Schema::dropIfExists('schemes');
        Schema::dropIfExists('angio_machines');
    }
};