<?php

namespace Tests\Feature;

use App\Actions\Contracts\SaveContractOccupantAction;
use App\Actions\Invoices\GenerateInvoiceAction;
use App\Enums\BillingMethod;
use App\Models\Contract;
use App\Models\ContractOccupant;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MeterReading;
use App\Models\PropertyService;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class InvoiceCollectionCycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_move_in_invoice_prorates_rent_and_does_not_require_current_month_readings(): void
    {
        $contract = $this->contract();
        $this->service($contract);

        $invoice = $this->generate($contract, '2026-10');

        $this->assertSame('2026-10-15', $invoice->due_date->toDateString());
        $this->assertSame('1700000.00', $invoice->total);
        $this->assertCount(1, $invoice->items);
        $this->assertSame(17, $invoice->items->first()->metadata['days']);
        $this->assertSame('2026-10-15', $invoice->items->first()->metadata['charge_from']);
        $this->assertSame('2026-10-31', $invoice->items->first()->metadata['charge_to']);
        $this->assertSame('3100000.00', $invoice->items->first()->metadata['monthly_rent']);
        $this->assertSame('1700000.00', $invoice->items->first()->unit_price);
        $this->assertNull($invoice->snapshot['service_billing_period']);
    }

    public function test_november_collects_november_rent_and_october_utilities_at_october_prices(): void
    {
        $contract = $this->contract();
        $service = $this->service($contract);
        PropertyService::factory()->for($contract->room->property)->for($service)->create([
            'effective_from' => '2026-11-01', 'unit_price' => 9999,
        ]);
        $reading = MeterReading::factory()->for($contract->room)->for($service)->create(['billing_period' => '2026-10', 'usage' => 20]);
        MeterReading::factory()->for($contract->room)->for($service)->create(['billing_period' => '2026-11', 'usage' => 99]);

        $preview = app(GenerateInvoiceAction::class)->preview($contract->room->property->landlord, ['contract_id' => $contract->id, 'billing_period' => '2026-11']);
        $invoice = $this->generate($contract, '2026-11');

        $this->assertSame('2026-11-05', $invoice->due_date->toDateString());
        $this->assertSame('3170000.00', $invoice->total);
        $this->assertSame($preview['attributes']['total'], $invoice->total);
        $this->assertSame('2026-10', $invoice->snapshot['service_billing_period']);
        $this->assertSame('2026-11', $invoice->items->firstWhere('type', 'rent')->metadata['billing_period']);
        $utility = $invoice->items->firstWhere('type', 'metered');
        $this->assertSame('2026-11-01', $invoice->items->firstWhere('type', 'rent')->metadata['charge_from']);
        $this->assertSame('2026-11-30', $invoice->items->firstWhere('type', 'rent')->metadata['charge_to']);
        $this->assertSame('2026-10-15', $utility->metadata['charge_from']);
        $this->assertSame('2026-10-31', $utility->metadata['charge_to']);
        $this->assertSame($preview['items'][1]['metadata']['charge_from'], $utility->metadata['charge_from']);
        $this->assertSame('2026-10', $utility->metadata['billing_period']);
        $this->assertSame('3500.00', $utility->unit_price);
        $this->assertSame($reading->id, $utility->meter_reading_id);
    }

    public function test_current_month_reading_cannot_replace_missing_previous_month_reading(): void
    {
        $contract = $this->contract();
        $service = $this->service($contract);
        MeterReading::factory()->for($contract->room)->for($service)->create(['billing_period' => '2026-11']);

        try {
            $this->generate($contract, '2026-11');
            $this->fail('Current usage month was charged in advance.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('invoices.errors.missing_reading', ['service' => $service->name, 'period' => '2026-10'])], $exception->errors()['billing_period']);
        }

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_per_person_water_uses_previous_month_occupancy(): void
    {
        $contract = $this->contract();
        $this->service($contract, BillingMethod::PerPerson);
        ContractOccupant::factory()->for($contract)->create(['move_in_date' => '2026-10-15']);

        $invoice = $this->generate($contract, '2026-11');

        $water = $invoice->items->firstWhere('type', 'per_person');
        $this->assertSame(17, $water->metadata['person_days']);
        $this->assertSame('2026-10-15', $water->metadata['charge_from']);
        $this->assertSame('2026-10-31', $water->metadata['charge_to']);
        $this->assertSame('17000.00', $water->amount);
        $this->assertSame('3117000.00', $invoice->total);
    }

    public function test_final_utilities_can_be_collected_next_month_without_charging_rent(): void
    {
        $contract = $this->contract();
        $contract->update(['status' => 'terminated', 'terminated_at' => '2026-10-25']);
        $service = $this->service($contract);
        MeterReading::factory()->for($contract->room)->for($service)->create(['billing_period' => '2026-10', 'usage' => 10]);

        $invoice = $this->generate($contract, '2026-11');

        $this->assertSame('35000.00', $invoice->total);
        $this->assertNull($invoice->items->firstWhere('type', 'rent'));
        $this->assertCount(1, $invoice->items);
        $this->assertSame('2026-10-15', $invoice->items->first()->metadata['charge_from']);
        $this->assertSame('2026-10-24', $invoice->items->first()->metadata['charge_to']);
        $this->expectException(ValidationException::class);
        $this->generate($contract, '2026-12');
    }

    #[TestWith(['2026-02-15', '2026-02', '1550000.00', '2026-02-28'])]
    #[TestWith(['2024-02-15', '2024-02', '1603448.28', '2024-02-29'])]
    public function test_first_rent_uses_actual_month_length_and_due_day_is_clamped(string $start, string $period, string $amount, string $due): void
    {
        $contract = $this->contract();
        $contract->update(['start_date' => $start, 'payment_due_day' => 31]);

        $invoice = $this->generate($contract, $period);

        $this->assertSame($amount, $invoice->total);
        $this->assertSame($due, $invoice->due_date->toDateString());
    }

    public function test_rent_only_move_in_invoice_does_not_lock_unbilled_occupancy(): void
    {
        $contract = $this->contract();
        $this->generate($contract, '2026-10');

        $occupant = app(SaveContractOccupantAction::class)->handle($contract->room->property->landlord, $contract, [
            'full_name' => 'New resident', 'move_in_date' => '2026-10-15',
        ]);

        $this->assertDatabaseHas('contract_occupants', ['id' => $occupant->id, 'contract_id' => $contract->id]);
    }

    public function test_issued_service_month_locks_occupancy_even_when_collection_is_next_month(): void
    {
        $contract = $this->contract();
        $this->service($contract, BillingMethod::PerPerson);
        $occupant = ContractOccupant::factory()->for($contract)->create(['move_in_date' => '2026-10-15']);
        $this->generate($contract, '2026-11');

        $this->expectException(ValidationException::class);
        app(SaveContractOccupantAction::class)->handle($contract->room->property->landlord, $contract, [
            'full_name' => $occupant->full_name, 'move_in_date' => '2026-10-16',
        ], $occupant);
    }

    public function test_legacy_invoice_services_are_not_charged_again_in_following_month(): void
    {
        $contract = $this->contract();
        $service = $this->service($contract);
        $legacy = Invoice::factory()->for($contract)->create(['landlord_id' => $contract->room->property->landlord_id, 'billing_period' => '2026-10']);
        InvoiceItem::factory()->for($legacy)->create(['type' => 'metered', 'metadata' => ['service_id' => $service->id]]);
        $legacy->update(['status' => 'issued']);

        $invoice = $this->generate($contract, '2026-11');

        $this->assertSame('3100000.00', $invoice->total);
        $this->assertCount(1, $invoice->items);
        $this->assertSame('2026-10', $legacy->fresh()->billing_period);
    }

    public function test_collection_month_before_move_in_is_rejected(): void
    {
        $contract = $this->contract();
        $this->expectException(ValidationException::class);
        $this->generate($contract, '2026-09');
    }

    public function test_move_in_before_collection_day_keeps_that_months_due_day(): void
    {
        $contract = $this->contract();
        $contract->update(['start_date' => '2026-10-02']);

        $invoice = $this->generate($contract, '2026-10');

        $this->assertSame('2026-10-05', $invoice->due_date->toDateString());
        $this->assertSame('3000000.00', $invoice->total);
    }

    public function test_checkout_on_first_day_collects_only_previous_month_services(): void
    {
        $contract = $this->contract();
        $contract->update(['status' => 'terminated', 'terminated_at' => '2026-11-01']);
        $service = $this->service($contract);
        MeterReading::factory()->for($contract->room)->for($service)->create(['billing_period' => '2026-10', 'usage' => 10]);

        $invoice = $this->generate($contract, '2026-11');

        $this->assertSame('35000.00', $invoice->total);
        $this->assertNull($invoice->items->firstWhere('type', 'rent'));
    }

    public function test_foreign_landlord_cannot_generate_new_cycle_invoice(): void
    {
        $contract = $this->contract();
        $this->expectException(ValidationException::class);
        app(GenerateInvoiceAction::class)->handle(User::factory()->create(), ['contract_id' => $contract->id, 'billing_period' => '2026-11']);
    }

    private function contract(): Contract
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-16 12:00:00'));

        return Contract::factory()->active()->create([
            'start_date' => '2026-10-15', 'end_date' => '2027-10-31', 'payment_due_day' => 5, 'rent_amount' => 3100000,
        ]);
    }

    private function service(Contract $contract, BillingMethod $method = BillingMethod::Metered): Service
    {
        $service = Service::factory()->for($contract->room->property->landlord, 'landlord')->create(['billing_method' => $method]);
        PropertyService::factory()->for($contract->room->property)->for($service)->create([
            'effective_from' => '2026-10-01', 'unit_price' => $method === BillingMethod::PerPerson ? 31000 : 3500,
        ]);

        return $service;
    }

    private function generate(Contract $contract, string $period): Invoice
    {
        return app(GenerateInvoiceAction::class)->handle($contract->room->property->landlord, ['contract_id' => $contract->id, 'billing_period' => $period]);
    }
}
