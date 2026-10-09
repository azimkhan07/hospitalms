<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPD/IPD workflow columns (PLAN.md section 18f):
 *
 * - vitals: the missing observation table, captured by reception (OPD),
 *   nurse/doctor (IPD) and reviewed in consultation.
 * - doctors.user_id: links a login to a doctor profile so a doctor only ever
 *   sees their own appointments.
 * - appointments: consultation state machine + complaint/diagnosis/follow-up.
 * - stays: discharge reason set + who discharged; DischargeHistory finally
 *   has rows to show.
 * - investigation_reports: ordered-by/appointment context so the lab has a
 *   pending queue instead of only BedReports' "already charged" entries.
 * - requested_appointments: approve creates the patient + real appointment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vitals')) {
            Schema::create('vitals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
                $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('stay_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('taken_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedSmallInteger('bp_systolic')->nullable();
                $table->unsignedSmallInteger('bp_diastolic')->nullable();
                $table->unsignedSmallInteger('pulse')->nullable();
                $table->decimal('temperature', 4, 1)->nullable();
                $table->unsignedTinyInteger('spo2')->nullable();
                $table->decimal('weight', 5, 2)->nullable();
                $table->decimal('height', 5, 2)->nullable();
                $table->string('note', 500)->nullable();
                $table->timestamp('taken_at');
                $table->timestamps();
                $table->softDeletes();
                $table->index(['tenant_id', 'taken_at']);
            });
        }

        if (Schema::hasTable('doctors') && ! Schema::hasColumn('doctors', 'user_id')) {
            Schema::table('doctors', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('employee_id')->constrained('users')->nullOnDelete();
            });
        }

        if (Schema::hasTable('appointments')) {
            if (! Schema::hasColumn('appointments', 'chief_complaint')) {
                Schema::table('appointments', function (Blueprint $table) {
                    $table->text('chief_complaint')->nullable()->after('notes');
                    $table->text('diagnosis')->nullable()->after('chief_complaint');
                    $table->date('follow_up_at')->nullable()->after('diagnosis');
                    $table->softDeletes();
                });
            }

            // widen: reception marks arrival (waiting), the doctor starts and
            // finishes the consult, a no-show is terminated.
            Schema::table('appointments', function (Blueprint $table) {
                $table->enum('status', [
                    'pending', 'confirmed', 'waiting', 'in_consult',
                    'completed', 'cancelled', 'terminated',
                ])->default('pending')->change();
            });
        }

        if (Schema::hasTable('prescriptions')) {
            if (! Schema::hasColumn('prescriptions', 'appointment_id')) {
                Schema::table('prescriptions', function (Blueprint $table) {
                    $table->foreignId('appointment_id')->nullable()->after('doctor_id')->constrained()->nullOnDelete();
                    $table->softDeletes();
                });
            }
        }

        if (Schema::hasTable('stays')) {
            if (! Schema::hasColumn('stays', 'discharged_by')) {
                Schema::table('stays', function (Blueprint $table) {
                    $table->foreignId('discharged_by')->nullable()->after('discharge_type')->constrained('users')->nullOnDelete();
                    $table->softDeletes();
                });
            }

            // PLAN.md 7: Recovered / Expired / LAMA-DAMA / Transferred, keeping
            // the legacy three so old discharge rows still display.
            Schema::table('stays', function (Blueprint $table) {
                $table->enum('discharge_type', [
                    'normal', 'referred', 'absconded',
                    'recovered', 'expired', 'lama', 'transferred',
                ])->nullable()->change();
            });
        }

        if (Schema::hasTable('investigation_reports')) {
            if (! Schema::hasColumn('investigation_reports', 'appointment_id')) {
                Schema::table('investigation_reports', function (Blueprint $table) {
                    $table->foreignId('appointment_id')->nullable()->after('patient_id')->constrained()->nullOnDelete();
                    $table->foreignId('ordered_by')->nullable()->after('reported_by')->constrained('users')->nullOnDelete();
                    $table->string('priority', 20)->default('routine')->after('is_urgent');
                });
            }
        }

        if (Schema::hasTable('requested_appointments')) {
            if (! Schema::hasColumn('requested_appointments', 'status')) {
                Schema::table('requested_appointments', function (Blueprint $table) {
                    $table->string('status', 20)->default('pending')->after('message');
                    $table->foreignId('patient_id')->nullable()->after('status')->constrained()->nullOnDelete();
                    $table->foreignId('appointment_id')->nullable()->after('patient_id')->constrained()->nullOnDelete();
                });
            }
        }

        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'deleted_at')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vitals');

        $drops = [
            'doctors' => ['user_id'],
            'appointments' => ['chief_complaint', 'diagnosis', 'follow_up_at', 'deleted_at'],
            'prescriptions' => ['appointment_id', 'deleted_at'],
            'stays' => ['discharged_by', 'deleted_at'],
            'investigation_reports' => ['appointment_id', 'ordered_by', 'priority'],
            'requested_appointments' => ['status', 'patient_id', 'appointment_id'],
            'payments' => ['deleted_at'],
        ];

        foreach ($drops as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
                }
            }
        }

        if (Schema::hasTable('appointments')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending')->change();
            });
        }

        if (Schema::hasTable('stays')) {
            Schema::table('stays', function (Blueprint $table) {
                $table->enum('discharge_type', ['normal', 'referred', 'absconded'])->nullable()->change();
            });
        }
    }
};
