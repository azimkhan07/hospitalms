<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some clinics/hospitals run their own medicine/lab home deliveries,
        // most do not. The Super Admin flips this on per facility; only then
        // does the Deliveries panel and the staff menu show up.
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('deliveries_enabled')->default(false)->after('mode');
        });

        // Staff events shown on the shared calendar: blood-donation camps,
        // visiting doctors and generic hospital events. Meetings stay on the
        // meeting calendar; both render on the same grid.
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->string('title', 120);
            $table->string('type', 20)->default('general'); // blood_camp | visiting | general
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('color', 7)->default('#0f7fd4');
            $table->string('description', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['starts_at']);
        });

        // Home-delivery orders for a facility that has deliveries enabled.
        // Lifecycle: pending -> assigned -> out_for_delivery -> delivered,
        // with cancel as an escape hatch. Status moves are legal only in that
        // direction (DeliveryService enforces the map).
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('patient_id')->constrained();
            $table->string('order_type', 20)->default('medicine'); // medicine | lab | general
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('address', 255)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('rider_name', 120)->nullable();
            $table->string('vehicle', 40)->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->string('status', 20)->default('pending'); // pending|assigned|out_for_delivery|delivered|cancelled
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['status', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('calendar_events');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('deliveries_enabled');
        });
    }
};