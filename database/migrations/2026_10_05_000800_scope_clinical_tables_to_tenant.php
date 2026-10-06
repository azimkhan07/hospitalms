<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Puts every clinical, staff and site-record table inside a facility.
 *
 * Until this ran, only the tables added for the tenant work (wards, beds,
 * machines, rate card, staff accounts) carried tenant_id. Patients,
 * appointments, prescriptions, bills and the HR tables did not, so the admin
 * panel showed one facility's patients to the other facility's staff -- the one
 * failure a hospital cannot ship with.
 *
 * Existing rows are filed under the facility they belong to, worked out from
 * the row they point at (a bill from its patient, a prescription item from its
 * prescription, a nurse from the staff account with the same email). Rows with
 * no such link -- a patient, a medicine -- have no way to tell, so they go to
 * the oldest facility and the operator re-files them; on an empty install this
 * is a no-op.
 */
return new class extends Migration
{
    /** table => the column that points at another table we can learn the tenant from */
    private array $via = [
        'appointments' => 'patient_id:patients',
        'requested_appointments' => null, // filled from the matching staff account
        'stays' => 'patient_id:patients',
        'bills' => 'patients_id:patients', // this table names it patients_id
        'payments' => 'patient_id:patients',
        'prescriptions' => 'patient_id:patients',
        'prescription_items' => 'prescription_id:prescriptions',
        'patient_checkups' => 'patient_id:patients',
        'birthreports' => 'patient_id:patients',
        'operationreports' => 'patient_id:patients',
        'medicines' => null,
        'employees' => null,
        'doctors' => 'employee_id:employees',
        'nurses' => null,
        'hods' => 'doctor_id:doctors',
        'blocks' => null,
        'leave_requests' => 'user_id:users',
        'meetings' => 'created_by:users',
        'meeting_participants' => 'user_id:users',
        'contacts' => null,
        'subscribers' => null,
    ];

    /** rows that name a person by email, and the account table to match */
    private array $viaEmail = [
        'requested_appointments' => 'email',
        'employees' => 'email',
        'nurses' => 'email',
    ];

    private array $tables = [
        'patients', 'appointments', 'requested_appointments', 'stays', 'bills', 'payments',
        'prescriptions', 'prescription_items', 'patient_checkups', 'birthreports',
        'operationreports', 'medicines', 'employees', 'doctors', 'nurses', 'hods', 'blocks',
        'leave_requests', 'meetings', 'meeting_participants', 'contacts', 'subscribers',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (! Schema::hasColumn($table, 'tenant_id')) {
                    $blueprint->unsignedBigInteger('tenant_id')->nullable()->after('id');
                }
            });

            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                // An index on its own; the named index is added below once every
                // table has the column, to keep the two lists in one place.
                $blueprint->index('tenant_id', 'idx_tenant');
            });
        }

        $fallback = DB::table('tenants')->orderBy('id')->value('id');

        if (! $fallback) {
            return;
        }

        foreach ($this->tables as $table) {
            if (($link = $this->via[$table] ?? null) !== null) {
                [$column, $parent] = explode(':', $link);

                DB::table($table)
                    ->whereNull('tenant_id')
                    ->whereIn($column, DB::table($parent)->select('id')->whereNotNull('tenant_id'))
                    ->update(['tenant_id' => DB::table($parent)->select('tenant_id')->whereColumn($parent.'.id', $table.'.'.$column)]);
            }

            if (($column = $this->viaEmail[$table] ?? null) !== null) {
                DB::table($table)
                    ->whereNull('tenant_id')
                    ->whereIn($column, DB::table('users')->select('email')->whereNotNull('tenant_id'))
                    ->update(['tenant_id' => DB::table('users')->select('tenant_id')->whereColumn('users.email', $table.'.'.$column)]);
            }

            // Whatever is still unfiled has no provable owner. Rather than leave
            // it null -- where no facility would ever list it -- it goes to the
            // oldest facility, where an operator can re-file it.
            DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $fallback]);
        }

        foreach ($this->tables as $table) {
            DB::statement(
                'ALTER TABLE `'.$table.'` ADD CONSTRAINT `'.$table.'_tenant_id_foreign`'
                .' FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE'
            );
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            DB::statement('ALTER TABLE `'.$table.'` DROP FOREIGN KEY `'.$table.'_tenant_id_foreign`');
        }

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex('idx_tenant');
                $blueprint->dropColumn('tenant_id');
            });
        }
    }
};
