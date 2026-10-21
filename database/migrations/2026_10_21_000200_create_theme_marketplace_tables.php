<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 8)->default('PKR');
            $table->string('primary_color', 20)->default('#0f7fd4');
            $table->string('accent_color', 20)->default('#0b3c66');
            $table->string('dark_color', 20)->default('#082c4d');
            $table->boolean('is_premium')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('theme_key', 60)->nullable()->after('clinic_type_id')->index();
        });

        Schema::create('theme_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('theme_key', 60)->index();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 8)->default('PKR');
            $table->string('method', 30)->default('manual');
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'theme_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_payments');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('theme_key');
        });

        Schema::dropIfExists('themes');
    }
};
