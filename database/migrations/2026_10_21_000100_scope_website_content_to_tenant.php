<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Website content used to be one global set of rows: every facility's public
 * site read the same settings, features and testimonials. The rows now carry a
 * tenant_id so each facility's admin can manage its own site.
 *
 * Existing rows stay valid: settings rows with a NULL tenant_id are the
 * platform defaults a facility falls back to until its admin saves its own
 * values. Feature/testimonial rows are moved to the first facility because
 * they were written for the original (single) hospital site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index(['tenant_id', 'key']);
        });

        foreach (['features', 'testimonials'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
        }

        $tenantId = DB::table('tenants')->orderBy('id')->value('id');

        if ($tenantId) {
            foreach (['features', 'testimonials'] as $table) {
                DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'key']);
            $table->dropColumn('tenant_id');
        });

        foreach (['features', 'testimonials'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
