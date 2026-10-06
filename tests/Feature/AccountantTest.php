<?php

namespace Tests\Feature;

use App\Models\AccountingVoucher;
use App\Models\bill;
use App\Models\employee;
use App\Models\patient;
use App\Models\payment;
use App\Models\Role;
use App\Models\SalaryVoucher;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 8 acceptance: the accountant desk keeps the patient bills, books
 * every payment as income on the ledger, and runs monthly payrolls whose
 * payments become expenses (PLAN.md 11).
 */
class AccountantTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'Finance Hospital', 'slug' => 'finance-hospital',
            'mode' => 'hospital', 'status' => 'active', 'geo_radius_meters' => 200,
        ]);
        $this->tenant->syncRequiredRoles([
            'admin', 'moderator', 'doctor', 'nurse', 'pharmacist',
            'storekeeper', 'accountant', 'hr', 'laboratorist', 'receptionist',
        ]);
    }

    protected function staff(string $slug): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'is_active' => true,
        ]);
    }

    protected function make(string $model, array $attributes)
    {
        $row = new $model;
        $row->tenant_id = $this->tenant->id;
        $row->fill($attributes);
        $row->save();

        return $row;
    }

    protected function patient(): patient
    {
        return $this->make(patient::class, ['name' => 'Rukhsar', 'phone' => '0300-0000001']);
    }

    protected function openBill(patient $patient, float $amount = 120.00): bill
    {
        return $this->make(bill::class, [
            'patients_id' => $patient->id,
            'status' => 'unpaid',
            'amount' => $amount,
            'tax' => 0,
            'discount' => 0,
            'invoice_no' => 'INV-11',
        ]);
    }

    protected function staffWithSalary(string $name, string $salary): employee
    {
        return $this->make(employee::class, [
            'name' => $name,
            'email' => strtolower($name).'@hms.com',
            'phone' => '0300-0000002',
            'salary' => $salary,
            'position' => 'accountant',
            'status' => 'active',
        ]);
    }

    public function test_only_finance_roles_can_open_the_accountant_desk(): void
    {
        $this->actingAs($this->staff('accountant'));

        Livewire::test(\App\Http\Livewire\Admins\Accounts::class)
            ->assertSee('Accountant Desk');

        $this->actingAs($this->staff('doctor'));

        Livewire::test(\App\Http\Livewire\Admins\Accounts::class)
            ->assertForbidden();
    }

    public function test_recording_a_payment_marks_the_invoice_paid_and_books_income(): void
    {
        $patient = $this->patient();
        $bill = $this->openBill($patient, 120.00);

        $this->actingAs($this->staff('accountant'));

        Livewire::test(\App\Http\Livewire\Admins\Accounts::class)
            ->call('accept', $bill->id)
            ->assertSet('receiveBillId', $bill->id)
            ->assertSeeHtml('placeholder="Amount"')
            ->set('receiveAmount', 120.00)
            ->set('receiveMode', 'cash')
            ->call('recordPayment')
            ->assertHasNoErrors();

        $this->assertSame('paid', $bill->fresh()->status);
        $this->assertNotNull($bill->fresh()->paid_at);
        $this->assertSame(0.0, $bill->fresh()->amountDue());

        $payment = payment::latest('id')->first();
        $this->assertSame((string) $bill->patients_id, (string) $payment->patient_id);
        $this->assertSame('120.00', (string) $payment->amount);
        $this->assertSame('paid', $payment->status);

        $voucher = AccountingVoucher::where('type', 'income')->first();
        $this->assertNotNull($voucher);
        $this->assertSame('120.00', (string) $voucher->amount);
        $this->assertSame('payment', $voucher->ref_type);
        $this->assertSame($payment->id, $voucher->ref_id);

        $this->assertSame(0, (int) round(app(\App\Services\Accounting::class)->outstanding()));
    }

    public function test_a_partial_payment_keeps_the_invoice_open(): void
    {
        $patient = $this->patient();
        $bill = $this->openBill($patient, 120.00);

        $this->actingAs($this->staff('accountant'));

        Livewire::test(\App\Http\Livewire\Admins\Accounts::class)
            ->call('accept', $bill->id)
            ->set('receiveAmount', 50.00)
            ->call('recordPayment')
            ->assertHasNoErrors();

        $this->assertSame('unpaid', $bill->fresh()->status);
        $this->assertSame(70.0, $bill->fresh()->amountDue());
    }

    public function test_payroll_generates_a_due_voucher_per_salaried_staff(): void
    {
        $asia = $this->staffWithSalary('Asia Bibi', '80000');
        $bilal = $this->staffWithSalary('Bilal Ahmed', '60000');
        $this->make(employee::class, [
            'name' => 'No Salary', 'email' => 'nosalary@hms.com', 'phone' => '0300-0000003',
            'salary' => null, 'position' => 'other', 'status' => 'active',
        ]);

        $this->actingAs($this->staff('accountant'));

        Livewire::test(\App\Http\Livewire\Admins\Accounts::class)
            ->set('month', '2026-10')
            ->call('generatePayroll')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('salary_vouchers', 2);
        $this->assertSame(2, SalaryVoucher::where('month', '2026-10')->where('status', 'due')->count());
        $this->assertSame(
            '80000.00',
            (string) SalaryVoucher::where('month', '2026-10')->where('employee_id', $asia->id)->value('gross')
        );
        $this->assertSame(
            '60000.00',
            (string) SalaryVoucher::where('month', '2026-10')->where('employee_id', $bilal->id)->value('net')
        );

        // Re-running the same month is a no-op.
        Livewire::test(\App\Http\Livewire\Admins\Accounts::class)
            ->set('month', '2026-10')
            ->call('generatePayroll');

        $this->assertDatabaseCount('salary_vouchers', 2);
    }

    public function test_paying_a_salary_books_an_expense_and_clears_the_due(): void
    {
        $person = $this->staffWithSalary('Daniyal Khan', '70000');

        $this->actingAs($this->staff('accountant'));

        Livewire::test(\App\Http\Livewire\Admins\Accounts::class)
            ->set('month', '2026-10')
            ->call('generatePayroll');

        $voucher = SalaryVoucher::where('month', '2026-10')->first();

        Livewire::test(\App\Http\Livewire\Admins\Accounts::class)
            ->call('paySalary', $voucher->id)
            ->assertHasNoErrors();

        $this->assertSame('paid', $voucher->fresh()->status);
        $this->assertNotNull($voucher->fresh()->paid_at);

        $expense = AccountingVoucher::where('type', 'expense')->where('ref_type', 'salary_voucher')->first();
        $this->assertNotNull($expense);
        $this->assertSame($voucher->id, $expense->ref_id);
        $this->assertSame('70000.00', (string) $expense->amount);
        $this->assertStringContainsString('Daniyal Khan', $expense->title);

        $this->assertSame(0.0, \App\Services\Accounting::salariesDue('2026-10'));
    }

    public function test_paying_an_already_paid_salary_is_rejected(): void
    {
        $person = $this->staffWithSalary('Ehsan Ali', '50000');

        $this->make(SalaryVoucher::class, [
            'employee_id' => $person->id,
            'gross' => 50000, 'deductions' => 0, 'net' => 50000,
            'month' => '2026-10', 'status' => 'paid', 'paid_at' => now(),
        ]);

        $this->actingAs($this->staff('accountant'));

        $this->expectException(\RuntimeException::class);

        app(\App\Services\Accounting::class)->paySalary(
            SalaryVoucher::where('month', '2026-10')->first()
        );
    }

    public function test_the_accounting_api_mirrors_the_books(): void
    {
        $patient = $this->patient();
        $this->openBill($patient, 300.00);

        $person = $this->staffWithSalary('Faraz Mehmood', '40000');
        $this->make(SalaryVoucher::class, [
            'employee_id' => $person->id,
            'gross' => 40000, 'deductions' => 0, 'net' => 40000,
            'month' => '2026-10', 'status' => 'due',
        ]);

        $this->actingAs($this->staff('accountant'), 'sanctum');

        $this->getJson(route('api.v1.admin.accounting'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.outstanding', 300)
            ->assertJsonCount(1, 'data.open_invoices')
            ->assertJsonPath('data.open_invoices.0.invoice_no', 'INV-11')
            ->assertJsonPath('data.salaries_due', 40000);
    }
}