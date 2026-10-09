<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // OPD queue tokens + the moment a doctor calls a patient (PLAN 18h/i).
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedInteger('token')->nullable()->after('status');
            $table->timestamp('called_at')->nullable()->after('token');
            $table->index(['tenant_id', 'token']);
        });

        // Doctors can mark themselves on duty so reception only assigns
        // appointments to doctors who are actually seeing patients.
        Schema::table('doctors', function (Blueprint $table) {
            $table->boolean('on_duty')->default(true)->after('user_id');
        });

        DB::table('doctors')->update(['on_duty' => true]);
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'token']);
            $table->dropColumn(['token', 'called_at']);
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('on_duty');
        });
    }
};