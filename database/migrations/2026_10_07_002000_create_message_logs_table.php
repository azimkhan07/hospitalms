<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outbound SMS / WhatsApp audit trail. Every send attempt (log driver or a
 * real HTTP gateway) lands here so staff can see what went out and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('channel', 20)->default('sms'); // sms | whatsapp
            $table->string('phone', 40);
            $table->text('body');
            $table->string('status', 20)->default('sent'); // sent | failed
            $table->string('provider', 40)->nullable();
            $table->string('provider_ref', 100)->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};