<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('investigation_reports')) {
            Schema::table('investigation_reports', function (Blueprint $table) {
                if (! Schema::hasColumn('investigation_reports', 'barcode')) {
                    $table->string('barcode', 24)->nullable()->after('is_urgent');
                }
                if (! Schema::hasColumn('investigation_reports', 'sample_collected_at')) {
                    $table->timestamp('sample_collected_at')->nullable()->after('barcode');
                }
                if (! Schema::hasColumn('investigation_reports', 'sample_collected_by')) {
                    $table->foreignId('sample_collected_by')->nullable()->after('sample_collected_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('investigation_reports', 'file_path')) {
                    $table->string('file_path', 500)->nullable()->after('result');
                }
                if (! Schema::hasColumn('investigation_reports', 'is_critical')) {
                    $table->boolean('is_critical')->default(false)->after('file_path');
                }
                if (! Schema::hasColumn('investigation_reports', 'critical_note')) {
                    $table->text('critical_note')->nullable()->after('is_critical');
                }
            });

            if (! Schema::hasIndex('investigation_reports', ['barcode'])) {
                Schema::table('investigation_reports', function (Blueprint $table) {
                    $table->unique('barcode');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('investigation_reports')) {
            Schema::table('investigation_reports', function (Blueprint $table) {
                try {
                    $table->dropUnique(['barcode']);
                } catch (\Throwable $e) {
                    // ignore
                }
                foreach (['barcode', 'sample_collected_at', 'sample_collected_by', 'file_path', 'is_critical', 'critical_note'] as $col) {
                    if (Schema::hasColumn('investigation_reports', $col)) {
                        if ($col === 'sample_collected_by') {
                            try {
                                $table->dropForeign(['sample_collected_by']);
                            } catch (\Throwable $e) {
                            }
                        }
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
