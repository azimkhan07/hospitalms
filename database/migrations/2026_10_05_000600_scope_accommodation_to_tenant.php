<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accommodation and department tables had no tenant_id at all, so every
 * facility on the platform shared one departments/rooms/beds table. With a
 * single seeded tenant that never showed; the moment a second facility is sold
 * they would see each other's wards. This is the containment step -- clinical
 * tables (patients, appointments, stays) are listed in PLAN.md as follow-on.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['departments', 'rooms', 'beds'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            });
        }

        $tenantId = DB::table('tenants')->orderBy('id')->value('id');

        if ($tenantId) {
            // Existing rows belong to the only facility that existed before this
            // column did.
            foreach (['departments', 'rooms', 'beds'] as $tableName) {
                if (Schema::hasColumn($tableName, 'tenant_id')) {
                    DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
                }
            }
        }

        foreach (['departments', 'rooms', 'beds'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            DB::statement(
                'CREATE INDEX IF NOT EXISTS '.$tableName.'_tenant_id_index ON '.$tableName.' (tenant_id)'
            );
        }
    }

    public function down(): void
    {
        foreach (['departments', 'rooms', 'beds'] as $tableName) {
            if (Schema::hasColumn($tableName, 'tenant_id')) {
                DB::statement('DROP INDEX IF EXISTS '.$tableName.'_tenant_id_index');
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('tenant_id');
                });
            }
        }
    }
};