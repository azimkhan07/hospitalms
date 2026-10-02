<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // medicines: name + expiry so the pharmacy/expiry modules have real data
        $medicineCols = array_diff(
            ['name', 'expiry_date', 'batch_no', 'manufacturer', 'stock'],
            Schema::getColumnListing('medicines')
        );

        if ($medicineCols) {
            Schema::table('medicines', function (Blueprint $table) use ($medicineCols) {
                if (in_array('name', $medicineCols)) {
                    $table->string('name')->nullable();
                }
                if (in_array('expiry_date', $medicineCols)) {
                    $table->date('expiry_date')->nullable();
                }
                if (in_array('batch_no', $medicineCols)) {
                    $table->string('batch_no', 60)->nullable();
                }
                if (in_array('manufacturer', $medicineCols)) {
                    $table->string('manufacturer', 120)->nullable();
                }
                if (in_array('stock', $medicineCols)) {
                    $table->integer('stock')->nullable();
                }
            });
        }

        if (! Schema::hasTable('prescriptions')) {
            Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
                $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
                $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->enum('status', ['draft', 'issued', 'cancelled'])->default('issued');
                $table->timestamp('issued_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('prescription_items')) {
            Schema::create('prescription_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
                $table->string('medicine');
                $table->string('dosage', 120)->nullable();
                $table->string('frequency', 120)->nullable();
                $table->string('duration', 120)->nullable();
                $table->string('note', 255)->nullable();
                $table->timestamps();
            });
        }

        // stays -> discharge record
        $staysCols = array_diff(
            ['discharged_at', 'discharge_note', 'discharge_type'],
            Schema::getColumnListing('stays')
        );

        if ($staysCols) {
            Schema::table('stays', function (Blueprint $table) use ($staysCols) {
                if (in_array('discharged_at', $staysCols)) {
                    $table->timestamp('discharged_at')->nullable();
                }
                if (in_array('discharge_note', $staysCols)) {
                    $table->text('discharge_note')->nullable();
                }
                if (in_array('discharge_type', $staysCols)) {
                    $table->enum('discharge_type', ['normal', 'referred', 'absconded'])->nullable();
                }
            });
        }

        // appointments already has a status enum; widen it + add notes
        if (! in_array('notes', Schema::getColumnListing('appointments'), true)) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])
                    ->default('pending')
                    ->change();
                $table->text('notes')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('notes');
            $table->enum('status', ['pending', 'completed'])->default('pending')->change();
        });

        $staysCols = array_intersect(
            ['discharged_at', 'discharge_note', 'discharge_type'],
            Schema::getColumnListing('stays')
        );

        if ($staysCols) {
            Schema::table('stays', function (Blueprint $table) use ($staysCols) {
                $table->dropColumn($staysCols);
            });
        }

        $medicineCols = array_intersect(
            ['name', 'expiry_date', 'batch_no', 'manufacturer', 'stock'],
            Schema::getColumnListing('medicines')
        );

        if ($medicineCols) {
            Schema::table('medicines', function (Blueprint $table) use ($medicineCols) {
                $table->dropColumn($medicineCols);
            });
        }
    }
};