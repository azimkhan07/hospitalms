<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * machines.department and investigation_tests.department were created as
 * foreignId, which made them bigints. The rate card stores a plain label here
 * ("Radiology", "ICU", "Pathology") -- the same way PLAN.md 9d.1/9d.2 describe
 * the field -- so widen both to a string instead of forcing every facility to
 * have a departments row before it can price a test.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['machines', 'investigation_tests'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'department')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->string('department', 150)->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['machines', 'investigation_tests'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'department')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->unsignedBigInteger('department')->nullable()->change();
                });
            }
        }
    }
};