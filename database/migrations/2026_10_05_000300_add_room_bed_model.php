<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rooms and beds, per PLAN.md section 9b.
 *
 * A bed number now always exists: ward, ICU and private each get numbered beds,
 * so a bed map can be printed and a discharge frees exactly one bed. Private
 * rooms are a separate type with capacity 1 and are listed in their own section.
 *
 * Also adds the tenant-level private room yes/no + quantity that the Super
 * Admin answers during hospital onboarding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('private_room_enabled')->default(false)->after('geo_radius_meters');
            $table->unsignedInteger('private_room_count')->nullable()->after('private_room_enabled');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->string('floor')->nullable()->after('name');
            $table->unsignedSmallInteger('capacity')->default(1)->after('type');
            $table->decimal('daily_rate', 10, 2)->nullable()->after('capacity');
            $table->enum('type', ['ward', 'private', 'semi-private', 'general', 'icu'])->default('general')->change();
        });

        Schema::table('beds', function (Blueprint $table) {
            $table->string('bed_number')->nullable()->after('id');
            $table->enum('status', ['alloted', 'available', 'cleaning', 'reserved', 'maintenance'])->default('available')->change();
        });

        Schema::table('stays', function (Blueprint $table) {
            $table->unsignedBigInteger('bed_id')->nullable()->after('room_id');
        });

        // A bed number is only unique inside its room, so scope the index there.
        Schema::table('beds', function (Blueprint $table) {
            $table->unique(['room_id', 'bed_number'], 'beds_room_bed_number_unique');
        });

        Schema::table('stays', function (Blueprint $table) {
            $table->index('bed_id');
        });

        // Backfill with plain SQL rather than the Eloquent models: at this point
        // the schema has already changed but the models may not know about the
        // new columns yet, and a migration must not depend on application code.
        \Illuminate\Support\Facades\DB::statement("UPDATE hms.rooms SET capacity = GREATEST(1, (SELECT COUNT(*) FROM hms.beds b WHERE b.room_id = hms.rooms.id AND b.deleted_at IS NULL)) WHERE capacity IS NULL OR capacity < 1");

        \Illuminate\Support\Facades\DB::statement("UPDATE hms.rooms SET name = CONCAT(UPPER(type), '-', id) WHERE name IS NULL OR name = ''");

        // Number every existing bed per PLAN.md section 9b.
        \Illuminate\Support\Facades\DB::statement("
            UPDATE hms.beds b
            JOIN (
                SELECT id,
                       ROW_NUMBER() OVER (PARTITION BY room_id ORDER BY id) AS rn
                FROM hms.beds
            ) n ON n.id = b.id
            JOIN hms.rooms r ON r.id = b.room_id
            SET b.bed_number = CASE r.type
                    WHEN 'icu' THEN CONCAT('ICU', n.rn)
                    WHEN 'private' THEN CONCAT('P', n.rn)
                    ELSE CONCAT('G', n.rn)
                END
            WHERE b.bed_number IS NULL OR b.bed_number = ''
        ");
    }

    public function down(): void
    {
        Schema::table('stays', function (Blueprint $table) {
            $table->dropIndex(['bed_id']);
            $table->dropColumn('bed_id');
        });

        Schema::table('beds', function (Blueprint $table) {
            $table->dropUnique('beds_room_bed_number_unique');
            $table->dropColumn('bed_number');
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['name', 'floor', 'capacity', 'daily_rate']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['private_room_enabled', 'private_room_count']);
        });
    }
};