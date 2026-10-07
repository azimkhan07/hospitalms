<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\bill;
use App\Models\CalendarEvent;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 11: a tamper-evident audit trail on the core records, readable from the
 * Super Admin panel and the platform API (PLAN.md 14).
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Audit Hospital', 'slug' => 'audit-hospital',
            'mode' => 'hospital', 'status' => 'active',
        ]);
        $this->tenant->syncRequiredRoles(['admin', 'moderator']);
    }

    protected function staff(string $slug): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'is_active' => true,
        ]);
    }

    protected function patient(string $name, string $phone = '0300-111222'): patient
    {
        $p = new patient(['name' => $name, 'phone' => $phone, 'status' => 'pending']);
        $p->tenant_id = $this->tenant->id;
        $p->save();

        return $p;
    }

    public function test_creating_a_patient_writes_an_audit_entry_with_the_actor(): void
    {
        $admin = $this->staff('admin');

        $this->actingAs($admin);
        $patient = $this->patient('Alice Audit');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'auditable_type' => patient::class,
            'auditable_id' => $patient->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $log = AuditLog::where('auditable_id', $patient->id)->where('action', 'created')->first();
        $this->assertSame('Alice Audit', $log->new['name']);
        $this->assertSame($admin->id, $log->user_id);
    }

    public function test_bill_payment_records_an_audit_trail(): void
    {
        $this->actingAs($this->staff('admin'));
        $patient = $this->patient('Bill Patient');

        $bill = bill::create([
            'patients_id' => $patient->id,
            'status' => 'unpaid',
            'amount' => 5000.00,
            'tax' => 0,
            'discount' => 0,
            'invoice_no' => 'INV-AUDIT-1',
        ]);

        $bill->update(['status' => 'paid', 'paid_at' => now()]);

        $trail = AuditLog::where('auditable_type', bill::class)
            ->where('auditable_id', $bill->id)->orderBy('id')->get();

        $this->assertSame(['created', 'updated'], $trail->pluck('action')->all());
        $this->assertSame('unpaid', $trail->last()->old['status']);
        $this->assertSame('paid', $trail->last()->new['status']);
    }

    public function test_deleting_a_calendar_event_is_audited(): void
    {
        $dean = $this->staff('moderator');

        Livewire::actingAs($dean)
            ->test(\App\Http\Livewire\Admins\Events::class)
            ->call('selectDate', '2026-12-18')
            ->set('title', 'Audit Camp')
            ->set('type', CalendarEvent::BLOOD_CAMP)
            ->set('startsAt', '09:30')
            ->call('save')
            ->assertHasNoErrors();

        $event = CalendarEvent::where('title', 'Audit Camp')->first();

        Livewire::actingAs($dean)
            ->test(\App\Http\Livewire\Admins\Events::class)
            ->call('removeEvent', $event->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deleted',
            'auditable_type' => CalendarEvent::class,
            'auditable_id' => $event->id,
            'summary' => "deleted Calendar Event Audit Camp",
        ]);
    }

    public function test_the_super_admin_sees_the_audit_trail_in_the_panel(): void
    {
        $this->actingAs($this->staff('admin'));
        $this->patient('Panel Patient');

        $super = User::factory()->create([
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
            'is_active' => true,
        ]);

        Livewire::actingAs($super)
            ->test(\App\Http\Livewire\SuperAdmin\AuditLogs::class)
            ->assertSee('Panel Patient')
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1);
    }

    public function test_the_platform_api_lists_audit_entries(): void
    {
        $admin = $this->staff('admin');
        $this->actingAs($admin);
        $this->patient('Api Patient');

        $super = User::factory()->create([
            'role_id' => Role::where('slug', 'super_admin')->value('id'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($super, 'sanctum')->getJson('/api/v1/superadmin/audit-logs');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.resource', 'patient')
            ->assertJsonPath('data.data.0.actor_id', $admin->id)
            ->assertJsonPath('data.data.0.tenant', 'Audit Hospital');
    }

    public function test_an_audit_write_never_breaks_the_business_write(): void
    {
        $this->actingAs($this->staff('admin'));
        $patient = $this->patient('Resilient Patient');

        $this->assertDatabaseHas('audit_logs', ['summary' => 'created Patient Resilient Patient']);
        $this->assertTrue($patient->exists);
    }

    public function test_the_backup_command_writes_a_snapshot_file(): void
    {
        $path = storage_path('app/backups/_test-snapshot.json');
        if (file_exists($path)) {
            unlink($path);
        }

        $exit = Artisan::call('hms:backup', ['--path' => $path]);

        $this->assertSame(0, $exit);
        $this->assertFileExists($path);

        $contents = json_decode(file_get_contents($path), true);
        $this->assertArrayHasKey('counts_per_tenant', $contents);
        $this->assertArrayHasKey('tables', $contents);
    }
}