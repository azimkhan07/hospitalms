<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 nursing clinical module:
 *
 * - drug_charts: one line per ordered medicine on an admitted stay.
 * - drug_chart_administrations: the nurse's administration log (given /
 *   skipped / refused) against each chart line.
 * - doctor_alerts: an escalation raised from the ward, acked by the doctor.
 * - handovers: the shift hand-over note passed between nursing teams.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_charts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('stay_id')->nullable()->constrained('stays')->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('medicine');
            $table->string('dosage')->nullable();
            $table->string('frequency')->nullable();
            $table->string('route')->nullable();
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->date('start_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('stay_id');
        });

        Schema::create('drug_chart_administrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('drug_chart_id')->constrained('drug_charts')->cascadeOnDelete();
            $table->timestamp('scheduled_time')->nullable();
            $table->timestamp('given_at')->nullable();
            $table->foreignId('given_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('state', 20)->default('due');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('drug_chart_id');
        });

        Schema::create('doctor_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('stay_id')->nullable()->constrained('stays')->cascadeOnDelete();
            $table->foreignId('raised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 30);
            $table->text('message');
            $table->boolean('is_urgent')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('resolved_at');
        });

        Schema::create('handovers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('from_user')->nullable()->constrained('users')->nullOnDelete();
            $table->string('to_role')->nullable();
            $table->string('ward')->nullable();
            $table->text('notes');
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handovers');
        Schema::dropIfExists('doctor_alerts');
        Schema::dropIfExists('drug_chart_administrations');
        Schema::dropIfExists('drug_charts');
    }
};
