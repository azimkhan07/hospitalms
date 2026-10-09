<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jitsi video meetings + a dashboard newsletter (PLAN.md section 12 / 18g).
 *
 * A meeting becomes a room: a Jitsi room id is generated on creation, the
 * organiser is the host (the only one who can start the call and record it),
 * and a target-role list decides who sees it in their meeting popup and
 * newsletter. Calendar events can spawn a meeting the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            if (! Schema::hasColumn('meetings', 'room_id')) {
                $table->string('room_id', 120)->nullable()->after('status');
            }
            if (! Schema::hasColumn('meetings', 'provider')) {
                $table->string('provider', 20)->default('jitsi')->after('room_id');
            }
            if (! Schema::hasColumn('meetings', 'link_url')) {
                $table->string('link_url', 255)->nullable()->after('provider');
            }
            if (! Schema::hasColumn('meetings', 'host_id')) {
                $table->unsignedBigInteger('host_id')->nullable()->after('created_by');
            }
            if (! Schema::hasColumn('meetings', 'target_roles')) {
                $table->json('target_roles')->nullable()->after('host_id');
            }
            if (! Schema::hasColumn('meetings', 'started_at')) {
                $table->dateTime('started_at')->nullable()->after('target_roles');
            }
            if (! Schema::hasColumn('meetings', 'recording_enabled')) {
                $table->boolean('recording_enabled')->default(false)->after('started_at');
            }
            if (! Schema::hasColumn('meetings', 'calendar_event_id')) {
                $table->unsignedBigInteger('calendar_event_id')->nullable()->after('recording_enabled');
            }
        });

        if (Schema::hasColumn('meetings', 'status')) {
            Schema::table('meetings', function (Blueprint $table) {
                $table->enum('status', ['scheduled', 'live', 'completed', 'cancelled'])
                    ->default('scheduled')->change();
            });
        }

        if (Schema::hasTable('calendar_events') && ! Schema::hasColumn('calendar_events', 'meeting_id')) {
            Schema::table('calendar_events', function (Blueprint $table) {
                $table->unsignedBigInteger('meeting_id')->nullable()->after('created_by');
            });
        }

        if (! Schema::hasTable('newsletters')) {
            Schema::create('newsletters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('title', 140);
                $table->text('body')->nullable();
                $table->string('type', 30)->default('meeting');
                $table->json('target_roles')->nullable();
                $table->boolean('important')->default(false);
                $table->unsignedBigInteger('meeting_id')->nullable();
                $table->unsignedBigInteger('calendar_event_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletters');

        if (Schema::hasTable('calendar_events') && Schema::hasColumn('calendar_events', 'meeting_id')) {
            Schema::table('calendar_events', function (Blueprint $table) {
                $table->dropColumn('meeting_id');
            });
        }

        Schema::table('meetings', function (Blueprint $table) {
            foreach (['calendar_event_id', 'recording_enabled', 'started_at', 'target_roles', 'host_id', 'link_url', 'provider', 'room_id'] as $column) {
                if (Schema::hasColumn('meetings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
