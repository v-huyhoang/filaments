<?php

namespace Tests\Feature;

use App\Actions\Invoices\CancelInvoiceAction;
use App\Actions\Invoices\GenerateInvoiceAction;
use App\Actions\MeterReadings\CorrectMeterReadingAction;
use App\Actions\Services\UpdateServiceAction;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\MeterReading;
use App\Models\PropertyService;
use App\Models\Service;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySqlBillingWorkflowTest extends TestCase
{
    private bool $transactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_MYSQL_WORKFLOW_TESTS') !== '1' || DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Opt-in MySQL integration tests: use RUN_MYSQL_WORKFLOW_TESTS=1 with an explicitly selected MySQL database. No migrations or resets are run.');
        }
        DB::beginTransaction();
        $this->transactionStarted = true;
        $this->freezeTime();
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    public function test_generated_key_rejects_second_live_revision_on_mysql(): void
    {
        $contract = Contract::factory()->active()->create();
        $original = $this->generate($contract);
        try {
            Invoice::factory()->for($contract)->create(['landlord_id' => $original->landlord_id, 'billing_period' => $original->billing_period, 'revision' => 2]);
            $this->fail('MySQL accepted a second live invoice.');
        } catch (UniqueConstraintViolationException $exception) {
            $this->assertSame(1062, $exception->errorInfo[1]);
            $this->assertStringContainsString('invoices_active_period_unique', $exception->getMessage());
        }
        $this->assertSame(1, $contract->invoices()->count());
    }

    public function test_cancel_correct_and_replace_on_mysql_preserves_old_snapshots(): void
    {
        $contract = Contract::factory()->active()->create(['rent_amount' => 3000000]);
        $configuration = PropertyService::factory()->for($contract->room->property)->create(['effective_from' => today()->startOfMonth(), 'unit_price' => 4000]);
        $reading = MeterReading::factory()->for($contract->room)->for($configuration->service)->create(['previous_value' => 100, 'current_value' => 125, 'usage' => 25]);
        $original = $this->generate($contract);
        app(CancelInvoiceAction::class)->handle($original->landlord, $original);
        app(CorrectMeterReadingAction::class)->handle($original->landlord, $reading, ['current_value' => 120, 'reason' => 'MySQL rollback-only integration test']);
        $replacement = $this->generate($contract, ['replaces_invoice_id' => $original->id, 'replacement_reason' => 'MySQL meter correction']);
        $this->assertSame('3100000.00', $original->fresh()->total);
        $this->assertSame('3080000.00', $replacement->total);
        $this->assertSame(2, $replacement->revision);
        $this->assertNull($original->fresh()->active_billing_key);
        $this->assertSame(1, $replacement->fresh()->active_billing_key);
        $this->assertSame(1, DB::table('meter_reading_corrections')->where('meter_reading_id', $reading->id)->count());
    }

    public function test_effective_service_status_query_on_mysql_keeps_earlier_billing(): void
    {
        $contract = Contract::factory()->active()->create(['start_date' => today()->subMonthsNoOverflow(2)->startOfMonth(), 'rent_amount' => 3000000]);
        $service = Service::factory()->for($contract->room->property->landlord, 'landlord')->create(['billing_method' => 'fixed']);
        PropertyService::factory()->for($contract->room->property)->for($service)->create(['effective_from' => today()->subMonthsNoOverflow(2)->startOfMonth(), 'fixed_price' => 100000]);
        app(UpdateServiceAction::class)->handle($contract->room->property->landlord, $service, [
            'name' => $service->name, 'code' => $service->code, 'billing_method' => 'fixed', 'unit' => $service->unit, 'is_active' => false,
        ]);
        $this->assertSame(1, PropertyService::query()->billingCandidates($contract->room->property->landlord, today()->subMonthNoOverflow()->endOfMonth())->where('service_id', $service->id)->count());
        $this->assertSame(0, PropertyService::query()->billingCandidates($contract->room->property->landlord, today()->endOfMonth())->where('service_id', $service->id)->count());
        $this->assertSame('3100000.00', $this->generate($contract, ['billing_period' => today()->subMonthNoOverflow()->format('Y-m')])->total);
        $this->assertSame('3000000.00', $this->generate($contract)->total);
    }

    /** @param array<string, mixed> $overrides */
    private function generate(Contract $contract, array $overrides = []): Invoice
    {
        return app(GenerateInvoiceAction::class)->handle($contract->room->property->landlord, ['contract_id' => $contract->id, 'billing_period' => today()->format('Y-m'), ...$overrides]);
    }
}
