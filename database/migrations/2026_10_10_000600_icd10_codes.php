<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ICD-10 diagnosis coding directory + the per-consult code link.
 *
 * Codes are shared medical knowledge, so tenant_id is nullable: the seeder
 * writes one global baseline (tenant_id NULL) and a facility may curate its
 * own additions alongside it. The appointment keeps the free-text diagnosis
 * and gains an optional coded reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('icd10_codes')) {
            Schema::create('icd10_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->index();
                $table->string('code', 10)->unique();
                $table->string('description', 300);
                $table->string('chapter', 120)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('appointments') && ! Schema::hasColumn('appointments', 'icd10_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->foreignId('icd10_id')->nullable()->after('diagnosis')->constrained('icd10_codes')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('appointments') && Schema::hasColumn('appointments', 'icd10_id')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('icd10_id');
            });
        }

        Schema::dropIfExists('icd10_codes');
    }
};
