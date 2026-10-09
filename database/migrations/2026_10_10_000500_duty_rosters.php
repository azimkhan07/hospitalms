<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weekly duty roster: one row per doctor (or department) per date, holding
     * the assigned shift. The unique key keeps a doctor on a single shift a
     * day; department rows carry a null doctor_id, which MySQL's unique index
     * treats as distinct (so several general rows can share a date).
     */
    public function up(): void
    {
        Schema::create('duty_rosters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->string('department')->nullable();
            $table->string('shift');
            $table->date('duty_date')->index();
            $table->string('start_time')->nullable();
            $table->string('end_time')->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'doctor_id', 'duty_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_rosters');
    }
};
