<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Existing tenants have no facility type yet, and the wizard now requires one.
 * Backfill from the mode so no facility becomes un-editable, and so the platform
 * list can be grouped by type straight away (PLAN.md §9c.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('clinic_types')->where('slug', 'general')->exists()) {
            return;
        }

        DB::table('tenants')
            ->whereNull('clinic_type_id')
            ->update([
                'clinic_type_id' => DB::table('clinic_types')->where('slug', 'general')->value('id'),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Nothing to undo: a type is only ever filled in, never removed.
    }
};