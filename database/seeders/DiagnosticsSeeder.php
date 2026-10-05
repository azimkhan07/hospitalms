<?php

namespace Database\Seeders;

use App\Models\beds;
use App\Models\InvestigationTest;
use App\Models\Machine;
use App\Models\Tenant;
use App\Models\rooms;
use Illuminate\Database\Seeder;

/**
 * Demo diagnostics setup, so the rate card and the ICU report have something to
 * show. Idempotent: re-running updates the same rows rather than piling up
 * duplicates.
 */
class DiagnosticsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Tenant::orderBy('id')->get() as $tenant) {
            $this->seedTenant($tenant);
        }
    }

    private function seedTenant(Tenant $tenant): void
    {
        $room = rooms::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'ICU-1'],
            [
                'department_id' => optional($this->department($tenant))->id,
                'type' => 'icu',
                'status' => 'available',
            ]
        );

        $bed = beds::firstOrCreate(
            ['tenant_id' => $tenant->id, 'bed_number' => 'ICU-A-01', 'room_id' => $room->id],
            ['status' => 'alloted']
        );

        $mri = $this->machine($tenant, [
            'name' => 'MRI 1.5T', 'code' => 'MRI-01', 'modality' => 'MRI',
            'department' => 'Radiology', 'location' => 'Basement Imaging',
            'vendor' => 'Siemens', 'status' => 'working', 'rate' => 2500,
            'warranty_ends_at' => now()->addYear()->toDateString(),
        ]);

        $vent = $this->machine($tenant, [
            'name' => 'ICU Ventilator 1', 'code' => 'VENT-01', 'modality' => 'Ventilator',
            'department' => 'ICU', 'location' => 'ICU Bay 1', 'status' => 'working',
            'rate' => 300, 'bed_id' => $bed->id,
        ]);

        $this->machine($tenant, [
            'name' => 'X-Ray Portable', 'code' => 'XRY-01', 'modality' => 'X-Ray',
            'department' => 'Radiology', 'status' => 'under_service', 'rate' => 400,
        ]);

        $this->test($tenant, [
            'name' => 'Brain MRI', 'code' => 'MRI-BR', 'machine_id' => $mri->id,
            'department' => 'Radiology', 'sample_type' => 'None',
            'turnaround_hours' => 24, 'base_rate' => 1500, 'per_unit_rate' => 500,
            'calc_type' => 'per_unit', 'urgent_factor' => 1.5, 'max_units' => 3,
        ]);

        $this->test($tenant, [
            'name' => 'Chest X-Ray', 'code' => 'XRY-CH', 'machine_id' => null,
            'department' => 'Radiology', 'turnaround_hours' => 2,
            'base_rate' => 450, 'per_unit_rate' => 0, 'calc_type' => 'flat',
            'urgent_factor' => 1.5, 'max_units' => 1,
        ]);

        $this->test($tenant, [
            'name' => 'Ventilator Day', 'code' => 'VENT-D', 'machine_id' => $vent->id,
            'department' => 'ICU', 'turnaround_hours' => 24,
            'base_rate' => 0, 'per_unit_rate' => 0, 'calc_type' => 'machine_rate',
            'urgent_factor' => 1.5, 'max_units' => 10,
        ]);

        $this->test($tenant, [
            'name' => 'Complete Blood Count', 'code' => 'CBC', 'machine_id' => null,
            'department' => 'Pathology', 'sample_type' => 'Blood',
            'turnaround_hours' => 6, 'base_rate' => 350, 'per_unit_rate' => 0,
            'calc_type' => 'flat', 'urgent_factor' => 1.5, 'max_units' => 1,
        ]);
    }

    private function department(Tenant $tenant)
    {
        return \App\Models\department::firstWhere('tenant_id', $tenant->id)
            ?? \App\Models\department::first();
    }

    private function machine(Tenant $tenant, array $attrs): Machine
    {
        return Machine::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => $attrs['code']],
            $attrs + ['tenant_id' => $tenant->id]
        );
    }

    private function test(Tenant $tenant, array $attrs): InvestigationTest
    {
        return InvestigationTest::updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => $attrs['code']],
            $attrs + ['tenant_id' => $tenant->id, 'is_active' => true]
        );
    }
}