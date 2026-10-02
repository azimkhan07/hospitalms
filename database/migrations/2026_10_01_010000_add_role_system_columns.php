<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->unsignedTinyInteger('level')->default(50)->after('slug');
            $table->text('description')->nullable()->after('level');
            $table->string('module')->nullable()->after('description');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('designation')->nullable()->after('phone');
            $table->string('department', 100)->nullable()->after('designation');
            $table->boolean('is_active')->default(true)->after('department');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->unsignedBigInteger('created_by')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'designation', 'department',
                'is_active', 'last_login_at', 'created_by',
            ]);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['slug', 'level', 'description', 'module']);
        });
    }
};