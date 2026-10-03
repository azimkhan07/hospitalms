<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per signed-in session. Presence is derived from login/logout, so
     * several rows can share a work_date when someone signs in more than once.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');

            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();

            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();

            $table->string('check_in_ip', 45)->nullable();
            $table->string('check_out_ip', 45)->nullable();

            // metres from the hospital, null while the tenant has no location set
            $table->unsignedInteger('check_in_distance_meters')->nullable();
            $table->unsignedInteger('check_out_distance_meters')->nullable();

            $table->string('source', 20)->default('login');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'work_date']);
            $table->index(['user_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
