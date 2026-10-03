<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the hospital physically is, so staff can be restricted to signing in
     * from the premises. Admin (the platform's tenant owner) is exempt because
     * they manage the system remotely.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('logo');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('geo_radius_meters')->default(200)->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'geo_radius_meters']);
        });
    }
};
