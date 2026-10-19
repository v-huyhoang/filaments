<?php

namespace Tests\Feature;

use App\Actions\Invoices\GenerateInvoiceAction;
use App\Enums\BillingMethod;
use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Models\Contract;
use App\Models\ContractOccupant;
use App\Models\Invoice;
use App\Models\MeterReading;
use App\Models\PropertyService;
use App\Models\Room;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class GenerateInvoiceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_issued_invoice_from_contract_rent_not_room_price_or_client_totals(): void
    {
        $contract = $this->activeContract();
        $invoice = $this->generate($contract, ['total' => 1, 'subtotal' => 1, 'status' => 'paid', 'due_date' => '2099-01-01', 'landlord_id' => User::factory()->create()->id]);
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame('3000000.00', $invoice->total);
        $this->assertSame($invoice->total, $invoice->subtotal);
        $this->assertSame($contract->room->property->landlord_id, $invoice->landlord_id);
        $this->assertSame(today()->startOfMonth()->addMonth()->day(5)->toDateString(), $invoice->due_date->toDateString());
        $this->assertCount(1, $invoice->items);
        $this->assertSame('3000000.00', $invoice->items->first()->unit_price);
        $this->assertSame($contract->tenant->full_name, $invoice->snapshot['tenant_name']);
    }

    public function test_bills_metered_fixed_and_actual_per_person_quantities(): void
    {
        $contract = $this->activeContract();
        $metered = $this->configure($contract, BillingMethod::Metered);
        $reading = MeterReading::factory()->for($contract->room)->for($metered->service)->create(['previous_value' => 100, 'current_value' => 125, 'usage' => 25]);
        $this->configure($contract, BillingMethod::Fixed);
        $this->configure($contract, BillingMethod::PerPerson);
        $this->configure($contract, BillingMethod::Manual);
        ContractOccupant::factory()->for($contract)->count(2)->create();
        $invoice = $this->generate($contract);
        $this->assertSame('3227500.00', $invoice->total);
        $this->assertCount(4, $invoice->items);
        $item = $invoice->items->firstWhere('type', 'metered');
        $this->assertSame('25.000', $item->quantity);
        $this->assertSame('87500.00', $item->amount);
        $this->assertSame($reading->id, $item->meter_reading_id);
        $this->assertSame($metered->id, $item->metadata['property_service_id']);
        $this->assertSame('2.000', $invoice->items->firstWhere('type', 'per_person')->quantity);
        $this->assertEquals($invoice->items->sum('amount'), $invoice->total);
    }

    public function test_rounds_each_line_half_up_before_summing(): void
    {
        $contract = $this->activeContract(['rent_amount' => '0.01']);
        foreach (range(1, 2) as $number) {
            $configuration = $this->configure($contract, BillingMethod::Metered, ['unit_price' => '0.01']);
            MeterReading::factory()->for($contract->room)->for($configuration->service)->create(['previous_value' => 0, 'current_value' => '0.5', 'usage' => '0.5']);
        }
        $invoice = $this->generate($contract);
        $this->assertSame('0.03', $invoice->total);
        $this->assertSame(['0.01', '0.01', '0.01'], $invoice->items->pluck('amount')->all());
    }

    public function test_uses_latest_effective_price_but_ignores_future_revision(): void
    {
        $contract = $this->activeContract();
        $configuration = $this->configure($contract, BillingMethod::Metered);
        $revision = PropertyService::factory()->for($contract->room->property)->for($configuration->service)->create(['unit_price' => 4000, 'effective_from' => today()->endOfMonth()]);
        PropertyService::factory()->for($contract->room->property)->for($configuration->service)->create(['unit_price' => 9999, 'effective_from' => today()->addMonthNoOverflow()->startOfMonth()]);
        MeterReading::factory()->for($contract->room)->for($configuration->service)->create(['usage' => 2]);
        $invoice = $this->generate($contract);
        $this->assertSame('3008000.00', $invoice->total);
        $this->assertSame($revision->id, $invoice->items->firstWhere('type', 'metered')->metadata['property_service_id']);
    }

    public function test_excludes_disabled_inactive_foreign_and_not_yet_effective_services(): void
    {
        $contract = $this->activeContract();
        $this->configure($contract, BillingMethod::Metered, ['is_enabled' => false]);
        $inactive = $this->configure($contract, BillingMethod::Metered);
        $inactive->service->update(['is_active' => false]);
        $this->configure($contract, BillingMethod::Metered, ['effective_from' => today()->addMonthNoOverflow()->startOfMonth()]);
        PropertyService::factory()->for($contract->room->property)->for(Service::factory()->create())->create();
        $this->assertCount(1, $this->generate($contract)->items);
    }

    public function test_disabled_latest_version_does_not_bill_enabled_history(): void
    {
        $contract = $this->activeContract();
        $configuration = $this->configure($contract, BillingMethod::Metered);
        PropertyService::factory()->for($contract->room->property)->for($configuration->service)->disabled()->create(['effective_from' => today()->endOfMonth()]);
        $this->assertCount(1, $this->generate($contract)->items);
    }

    public function test_missing_reading_fails_without_creating_invoice_or_items(): void
    {
        $contract = $this->activeContract();
        $configuration = $this->configure($contract, BillingMethod::Metered);
        MeterReading::factory()->for($configuration->service)->create();
        try {
            $this->generate($contract);
            $this->fail('Missing room reading was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('billing_period', $exception->errors());
            $this->assertSame(__('invoices.errors.missing_reading', ['service' => $configuration->service->name, 'period' => today()->format('Y-m')]), $exception->errors()['billing_period'][0]);
        }
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
    }

    public function test_per_person_service_requires_actual_count(): void
    {
        $contract = $this->activeContract();
        $this->configure($contract, BillingMethod::PerPerson);
        $this->expectException(ValidationException::class);
        $this->generate($contract);
    }

    public function test_reading_from_another_month_cannot_be_used(): void
    {
        $contract = $this->activeContract();
        $configuration = $this->configure($contract, BillingMethod::Metered);
        MeterReading::factory()->for($contract->room)->for($configuration->service)->create(['billing_period' => today()->subMonthNoOverflow()->format('Y-m')]);
        $this->expectException(ValidationException::class);
        $this->generate($contract);
    }

    public function test_duplicate_insert_after_precheck_becomes_validation_error_and_rolls_back(): void
    {
        $contract = $this->activeContract();
        Invoice::creating(function (Invoice $invoice): void {
            DB::table('invoices')->insert($invoice->getAttributes());
        });

        try {
            $this->generate($contract);
            $this->fail('Duplicate insert was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('invoices.errors.duplicate')], $exception->errors()['billing_period']);
        } finally {
            Event::forget('eloquent.creating: '.Invoice::class);
        }

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
    }

    public function test_duplicate_generation_is_rejected_and_database_enforces_business_key(): void
    {
        $contract = $this->activeContract();
        $invoice = $this->generate($contract);
        try {
            $this->generate($contract);
            $this->fail('Duplicate generation was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('billing_period', $exception->errors());
        }
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_items', 1);
        $this->expectException(UniqueConstraintViolationException::class);
        Invoice::factory()->for($contract)->create(['landlord_id' => $invoice->landlord_id, 'billing_period' => $invoice->billing_period]);
    }

    public function test_cross_landlord_contract_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(GenerateInvoiceAction::class)->handle(User::factory()->create(), ['contract_id' => $this->activeContract()->id, 'billing_period' => today()->format('Y-m')]);
    }

    public function test_contract_with_foreign_tenant_is_rejected(): void
    {
        $contract = $this->activeContract();
        $contract->update(['tenant_id' => Tenant::factory()->create()->id]);
        $this->expectException(ValidationException::class);
        $this->generate($contract);
    }

    #[TestWith(['draft'])]
    #[TestWith(['scheduled'])]
    #[TestWith(['expired'])]
    #[TestWith(['terminated'])]
    #[TestWith(['cancelled'])]
    public function test_inactive_contract_is_not_billable(string $status): void
    {
        $this->expectException(ValidationException::class);
        $this->generate($this->activeContract(['status' => $status]));
    }

    public function test_billing_month_outside_contract_dates_is_rejected(): void
    {
        $contract = $this->activeContract();
        $this->expectException(ValidationException::class);
        $this->generate($contract, ['billing_period' => today()->subMonthNoOverflow()->format('Y-m')]);
    }

    public function test_billing_after_contract_end_is_rejected(): void
    {
        $contract = $this->activeContract(['end_date' => today()->endOfMonth()]);
        $this->expectException(ValidationException::class);
        $this->generate($contract, ['billing_period' => today()->addMonthNoOverflow()->format('Y-m')]);
    }

    #[TestWith(['billing_period', '2026-13'])]
    #[TestWith(['billing_period', '2026-01-01'])]
    #[TestWith(['occupant_count', 0])]
    #[TestWith(['occupant_count', 1.5])]
    #[TestWith(['occupant_count', 5])]
    public function test_invalid_input_is_rejected(string $field, mixed $value): void
    {
        $contract = $this->activeContract();
        $this->expectException(ValidationException::class);
        $this->generate($contract, [$field => $value]);
    }

    public function test_direct_action_obeys_create_permission(): void
    {
        $contract = $this->activeContract();
        Gate::before(fn (User $user, string $ability): ?bool => $ability === 'create' ? false : null);
        $this->expectException(AuthorizationException::class);
        $this->generate($contract);
    }

    public function test_item_write_failure_rolls_back_invoice_and_already_inserted_items(): void
    {
        $contract = $this->activeContract();
        $item = ['type' => 'rent', 'name' => 'Rent', 'quantity' => 1, 'unit' => 'month', 'unit_price' => 1, 'amount' => 1, 'metadata' => []];
        $this->mock(InvoiceCalculator::class)->shouldReceive('calculate')->once()->andReturn([
            'items' => [$item, [...$item, 'name' => null]], 'subtotal' => '2.00', 'total' => '2.00',
        ]);
        try {
            $this->generate($contract);
            $this->fail('Invalid item was persisted.');
        } catch (QueryException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
    }

    public function test_rejects_amount_overflow_before_persisting_invoice(): void
    {
        $contract = $this->activeContract();
        $configuration = $this->configure($contract, BillingMethod::Metered, ['unit_price' => '9999999999.99']);
        MeterReading::factory()->for($contract->room)->for($configuration->service)->create(['usage' => '999999999.999']);
        $this->expectException(ValidationException::class);
        $this->generate($contract);
    }

    public function test_zero_rent_and_zero_usage_are_supported(): void
    {
        $contract = $this->activeContract(['rent_amount' => 0]);
        $configuration = $this->configure($contract, BillingMethod::Metered);
        MeterReading::factory()->for($contract->room)->for($configuration->service)->create(['usage' => 0]);
        $this->assertSame('0.00', $this->generate($contract)->total);
    }

    private function activeContract(array $overrides = []): Contract
    {
        $room = Room::factory()->create(['base_rent' => 9999999, 'max_occupants' => 4]);
        $tenant = Tenant::factory()->for($room->property->landlord, 'landlord')->create();

        return Contract::factory()->for($room)->for($tenant)->create([
            'status' => ContractStatus::Active, 'start_date' => today()->startOfMonth(), 'end_date' => today()->addYear(),
            'rent_amount' => '3000000.00', 'payment_due_day' => 5, ...$overrides,
        ]);
    }

    private function configure(Contract $contract, BillingMethod $method, array $overrides = []): PropertyService
    {
        $service = Service::factory()->for($contract->room->property->landlord, 'landlord')->create([
            'billing_method' => $method,
            'unit' => match ($method) {
                BillingMethod::Fixed => 'month',
                BillingMethod::PerPerson => 'person',
                BillingMethod::Manual => 'entry',
                BillingMethod::Metered => 'kWh',
            },
        ]);

        return PropertyService::factory()->for($contract->room->property)->for($service)->create([
            'effective_from' => today()->startOfMonth(),
            'unit_price' => match ($method) {
                BillingMethod::Metered => 3500,
                BillingMethod::PerPerson => 20000,
                default => 0,
            },
            'fixed_price' => $method === BillingMethod::Fixed ? 100000 : 0, ...$overrides,
        ]);
    }

    private function generate(Contract $contract, array $overrides = []): Invoice
    {
        return app(GenerateInvoiceAction::class)->handle($contract->room->property->landlord, [
            'contract_id' => $contract->id, 'billing_period' => today()->startOfMonth()->addMonth()->format('Y-m'), ...$overrides,
        ]);
    }
}
