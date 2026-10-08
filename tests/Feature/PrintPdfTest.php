<?php

namespace Tests\Feature;

use App\Models\bill;
use App\Models\medicine;
use App\Models\patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Print Center acceptance (PLAN.md 18d): one listing action back the centre,
 * and the four PDF papers -- A4 invoice, thermal daily medicine slip, A5 case
 * paper and A5 prescription -- all dispatch through the PrintService.
 */
class PrintPdfTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->tenant = Tenant::create([
            'name' => 'Print Hospital',
            'slug' => 'print-hospital',
            'mode' => 'hospital',
            'status' => 'active',
            'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles(['admin', 'moderator', 'doctor', 'nurse', 'receptionist', 'pharmacist', 'accountant', 'storekeeper', 'laboratorist', 'hr']);
    }

    private function staff(string $slug): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'is_active' => true,
        ]);
    }

    private function patient(string $name = 'Asif Raza'): patient
    {
        return patient::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'phone' => '0300-1234567',
            'gender' => 'male',
            'age' => '34',
            'address' => 'Street 2, Lahore',
        ]);
    }

    private function bill(patient $patient): bill
    {
        return bill::create([
            'tenant_id' => $this->tenant->id,
            'patients_id' => $patient->id,
            'status' => 'paid',
            'amount' => 12500,
            'tax' => 0,
            'discount' => 500,
            'invoice_no' => 'INV-0001',
            'paid_at' => now(),
            'issued_by' => $this->staff('admin')->id,
        ]);
    }

    private function prescription(patient $patient): Prescription
    {
        $rx = Prescription::create([
            'tenant_id' => $this->tenant->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->staff('doctor')->id,
            'status' => 'issued',
            'issued_at' => now(),
            'dispensed_at' => now(),
        ]);

        $drug = medicine::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Panadol Extra',
            'generic' => 'Paracetamol',
            'code' => 'PND-1',
            'price' => 35,
            'mrp' => 40,
            'batch_no' => 'B1',
            'expiry_date' => today()->addMonths(6),
            'stock' => 10,
            'quantity' => 10,
            'reorder_level' => 5,
        ]);

        PrescriptionItem::create([
            'tenant_id' => $this->tenant->id,
            'prescription_id' => $rx->id,
            'medicine' => $drug->name,
            'medicine_id' => $drug->id,
            'dosage' => '1 tab',
            'frequency' => '3 x daily',
            'duration' => '5',
            'dispensed_qty' => 2,
            'dispensed_at' => now(),
        ]);

        return $rx;
    }

    public function test_the_print_center_lists_invoices_with_filter_and_search(): void
    {
        $this->actingAs($this->staff('admin'));

        $ali = $this->patient('Ali Khan');
        $sara = $this->patient('Sara Bibi');
        $this->bill($ali);
        $this->bill($sara);

        $this->get(route('admin_print_center'))
            ->assertOk()
            ->assertSee('Ali Khan')
            ->assertSee('Sara Bibi')
            ->assertSee('INV-0001');

        $this->get(route('admin_print_center', ['type' => 'invoice', 'search' => 'Ali']))
            ->assertOk()
            ->assertSee('Ali Khan')
            ->assertDontSee('Sara Bibi');
    }

    public function test_the_print_center_lists_prescriptions_with_eager_loaded_items(): void
    {
        $this->actingAs($this->staff('admin'));

        $patient = $this->patient('Mahnoor');
        $this->prescription($patient);

        $this->get(route('admin_print_center', ['type' => 'prescription']))
            ->assertOk()
            ->assertSee('Mahnoor');
    }

    public function test_a_laboratorist_cannot_open_the_print_center(): void
    {
        $this->actingAs($this->staff('laboratorist'));

        $this->get(route('admin_print_center'))->assertForbidden();
    }

    public function test_the_invoice_pdf_downloads_as_a4(): void
    {
        $this->actingAs($this->staff('admin'));

        $patient = $this->patient('Usman');
        $this->bill($patient);

        $response = $this->get(route('admin_print_invoice', $this->bill($patient)));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('%PDF', $response->content());
    }

    public function test_the_medicine_slip_pdf_renders_today_s_dispenses(): void
    {
        $this->actingAs($this->staff('admin'));

        $patient = $this->patient('Bilal');
        $this->prescription($patient);

        $response = $this->get(route('admin_print_medicine_slip', $patient->id));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('%PDF', $response->content());
    }

    public function test_the_case_paper_pdf_downloads_as_a5(): void
    {
        $this->actingAs($this->staff('admin'));

        $patient = $this->patient('Sania');
        $this->prescription($patient);

        $response = $this->get(route('admin_print_case_paper', $patient->id));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('%PDF', $response->content());
    }

    public function test_the_prescription_pdf_downloads_as_a5(): void
    {
        $this->actingAs($this->staff('admin'));

        $patient = $this->patient('Hira');
        $rx = $this->prescription($patient);

        $response = $this->get(route('admin_print_prescription', $rx->id));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('%PDF', $response->content());
    }

    public function test_the_api_mirrors_the_print_center_register(): void
    {
        $admin = $this->staff('admin');

        // sign in so the tenant creating-hook stamps the rows below
        $this->actingAs($admin);

        $ali = $this->patient('Ali API');
        $this->bill($ali);

        $this->withToken($admin->createToken('test')->plainTextToken)
            ->getJson('/api/v1/admin/print-center?type=invoice&search=Ali')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'invoice')
            ->assertJsonPath('data.documents.total', 1);
    }
}
