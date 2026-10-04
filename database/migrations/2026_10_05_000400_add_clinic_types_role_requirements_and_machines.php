<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clinic_types')) {
            Schema::create('clinic_types', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 60)->unique();
                $table->string('name', 120);
                // hospital | clinic | both -- which tenant modes may use this type
                $table->string('applies_to', 20)->default('both');
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // The roles a facility actually bought, ticked by the Super Admin at
        // creation. Rows are a whitelist: a role with no row here cannot be
        // assigned or reached in that tenant (PLAN.md 9c.2).
        if (! Schema::hasTable('tenant_role_requirements')) {
            Schema::create('tenant_role_requirements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['tenant_id', 'role_id']);
            });
        }

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'clinic_type_id')) {
                $table->foreignId('clinic_type_id')->nullable()->after('mode');
            }
        });

        // Machines, and the tests they run. machines.tenant_id scopes them;
        // room_id / bed_id are what make ICU reporting work (PLAN.md 9d.3).
        if (! Schema::hasTable('machines')) {
            Schema::create('machines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name', 150);
                $table->string('code', 40)->nullable();
                // ECG, MRI, CT Scan, Ultrasound, ... (free text so a facility can
                // add a modality the seed does not know about)
                $table->string('modality', 80)->nullable();
                $table->foreignId('department')->nullable();
                $table->string('location', 150)->nullable();
                $table->string('serial_number', 120)->nullable();
                $table->string('vendor', 150)->nullable();
                $table->date('purchase_date')->nullable();
                $table->date('warranty_ends_at')->nullable();
                $table->string('status', 30)->default('working');
                $table->decimal('rate', 12, 2)->default(0);
                $table->foreignId('room_id')->nullable();
                $table->foreignId('bed_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('investigation_tests')) {
            Schema::create('investigation_tests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('machine_id')->nullable();
                $table->string('name', 150);
                $table->string('code', 40)->nullable();
                $table->foreignId('department')->nullable();
                $table->string('sample_type', 80)->nullable();
                $table->unsignedInteger('turnaround_hours')->default(24);
                // The rate card. A charge is computed from these, never typed
                // by hand (PLAN.md 9d.2).
                $table->decimal('base_rate', 12, 2)->default(0);
                $table->decimal('per_unit_rate', 12, 2)->default(0);
                // flat = base | per_unit = base + per_unit * units | machine = machine rate * units
                $table->string('calc_type', 20)->default('flat');
                $table->decimal('urgent_factor', 4, 2)->default(1.50);
                $table->unsignedInteger('max_units')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['tenant_id', 'is_active']);
            });
        }

        // One performed investigation: the calculated charge, the formula that
        // produced it, and optionally which bed/room it belongs to.
        if (! Schema::hasTable('investigation_reports')) {
            Schema::create('investigation_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('investigation_test_id')->constrained()->cascadeOnDelete();
                $table->foreignId('machine_id')->nullable();
                $table->foreignId('patient_id')->nullable();
                $table->foreignId('room_id')->nullable();
                $table->foreignId('bed_id')->nullable();
                $table->unsignedInteger('units')->default(1);
                $table->boolean('is_urgent')->default(false);
                $table->decimal('discount', 12, 2)->default(0);
                $table->decimal('tax_percent', 5, 2)->default(0);
                // kept so a printed report can show the working, and so a later
                // rate change does not silently rewrite history
                $table->decimal('charge', 12, 2)->default(0);
                $table->string('formula', 190)->nullable();
                $table->json('result')->nullable();
                $table->text('findings')->nullable();
                $table->string('status', 30)->default('pending');
                $table->foreignId('reported_by')->nullable();
                $table->timestamp('reported_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['tenant_id', 'bed_id']);
                $table->index(['tenant_id', 'room_id']);
            });
        }

        $this->seedTypes();
        $this->backfillRequirements();
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'clinic_type_id')) {
                $table->dropColumn('clinic_type_id');
            }
        });

        Schema::dropIfExists('investigation_reports');
        Schema::dropIfExists('investigation_tests');
        Schema::dropIfExists('machines');
        Schema::dropIfExists('tenant_role_requirements');
        Schema::dropIfExists('clinic_types');
    }

    private function seedTypes(): void
    {
        $types = [
            ['general', 'General', 'both', 1],
            ['multispeciality', 'Multispeciality', 'both', 2],
            ['dental', 'Dental', 'both', 3],
            ['skin', 'Skin / Dermatology', 'both', 4],
            ['eye', 'Eye / Ophthalmology', 'both', 5],
            ['ent', 'ENT', 'both', 6],
            ['orthopaedic', 'Orthopaedic', 'both', 7],
            ['paediatric', 'Paediatric', 'both', 8],
            ['gynaecology', 'Gynaecology', 'both', 9],
            ['cardiology', 'Cardiology', 'both', 10],
            ['neurology', 'Neurology', 'both', 11],
            ['oncology', 'Oncology', 'hospital', 12],
            ['nephrology', 'Nephrology / Dialysis', 'hospital', 13],
            ['dental_hospital', 'Dental Hospital', 'hospital', 14],
        ];

        foreach ($types as [$slug, $name, $applies, $order]) {
            DB::table('clinic_types')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'applies_to' => $applies,
                    'is_active' => true,
                    'sort_order' => $order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Existing tenants get the role set their mode already implied, so nothing
     * loses access the moment this lands: a clinic that suddenly had no doctor
     * would be un-sellable and the staff directory would be empty.
     */
    private function backfillRequirements(): void
    {
        $roles = DB::table('roles')->pluck('id', 'slug');

        $byMode = [
            'clinic' => ['admin', 'receptionist', 'doctor', 'pharmacist'],
            'hospital' => ['admin', 'moderator', 'receptionist', 'doctor', 'nurse',
                'pharmacist', 'laboratorist', 'storekeeper', 'accountant', 'hr'],
        ];

        $tenants = DB::table('tenants')->select(['id', 'mode'])->get();

        foreach ($tenants as $tenant) {
            // left unset: fall back to the mode, rather than locking the tenant
            // out of every module
            $slug = $byMode[$tenant->mode] ?? $byMode['clinic'];

            foreach ($slug as $roleSlug) {
                if (! isset($roles[$roleSlug])) {
                    continue;
                }
                DB::table('tenant_role_requirements')->updateOrInsert(
                    ['tenant_id' => $tenant->id, 'role_id' => $roles[$roleSlug]],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }
};