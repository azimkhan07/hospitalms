<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The doctor's "call next" bell (PLAN.md 18i) needs a called state
        // between waiting and in_consult.
        Schema::table('appointments', function (Blueprint $table) {
            $table->enum('status', [
                'pending', 'confirmed', 'waiting', 'called',
                'in_consult', 'completed', 'cancelled', 'terminated',
            ])->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->enum('status', [
                'pending', 'confirmed', 'waiting', 'in_consult',
                'completed', 'cancelled', 'terminated',
            ])->default('pending')->change();
        });
    }
};