<?php

namespace Tests\Feature;

use App\Actions\Contracts\SaveContractOccupantAction;
use App\Actions\Invoices\CancelInvoiceAction;
use App\Actions\Invoices\GenerateInvoiceAction;
use App\Enums\BillingMethod;
use App\Models\Contract;
use App\Models\ContractOccupant;
use App\Models\Invoice;
use App\Models\PropertyService;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class OccupancyBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_charges_two_full_month_residents_and_one_mid_month_arrival(): void
    {
        $contract = $this->contract('2026-10');
        ContractOccupant::factory()->for($contract)->count(2)->create();
        ContractOccupant::factory()->for($contract)->create(['move_in_date' => '2026-10-17']);

        $invoice = $this->invoice($contract, '2026-10');

        $item = $invoice->items->firstWhere('type', 'per_person');
        $this->assertSame('223548.00', $item->amount);
        $this->assertSame(77, $item->metadata['person_days']);
        $this->assertSame(31, $item->metadata['days_in_month']);
        $this->assertCount(3, $item->metadata['occupants']);
        $this->assertSame('3223548.00', $invoice->total);
        $this->assertNull($item->meter_reading_id);
    }

    #[TestWith(['2026-02', '2026-02-15', '45000.00', 14])]
    #[TestWith(['2024-02', '2024-02-15', '46552.00', 15])]
    #[TestWith(['2026-04', '2026-04-16', '45000.00', 15])]
    #[TestWith(['2026-10', '2026-10-17', '43548.00', 15])]
    public function test_uses_actual_calendar_days_including_leap_year(string $period, string $arrival, string $amount, int $days): void
    {
        $contract = $this->contract($period);
        ContractOccupant::factory()->for($contract)->create(['move_in_date' => $arrival]);

        $item = $this->invoice($contract, $period)->items->firstWhere('type', 'per_person');

        $this->assertSame($amount, $item->amount);
        $this->assertSame($days, $item->metadata['person_days']);
    }

    public function test_counts_move_in_but_not_move_out_and_does_not_double_charge_handover(): void
    {
        $contract = $this->contract('2026-10');
        ContractOccupant::factory()->for($contract)->create(['move_out_date' => '2026-10-17']);
        ContractOccupant::factory()->for($contract)->create(['move_in_date' => '2026-10-17']);
        ContractOccupant::factory()->for($contract)->create(['move_in_date' => '2026-10-10', 'move_out_date' => '2026-10-10']);

        $item = $this->invoice($contract, '2026-10')->items->firstWhere('type', 'per_person');

        $this->assertSame('90000.00', $item->amount);
        $this->assertSame(31, $item->metadata['person_days']);
        $this->assertSame([16, 15], array_column($item->metadata['occupants'], 'days'));
    }

    public function test_arrival_on_tenth_and_departure_on_twentieth_charges_ten_days(): void
    {
        $contract = $this->contract('2026-10');
        ContractOccupant::factory()->for($contract)->create(['move_in_date' => '2026-10-10', 'move_out_date' => '2026-10-20']);

        $item = $this->invoice($contract, '2026-10')->items->firstWhere('type', 'per_person');

        $this->assertSame('29032.00', $item->amount);
        $this->assertSame(10, $item->metadata['person_days']);
        $this->assertSame('2026-10-10', $item->metadata['occupants'][0]['from']);
        $this->assertSame('2026-10-20', $item->metadata['occupants'][0]['until']);
    }

    public function test_rounds_once_after_all_person_days_not_each_person_or_day(): void
    {
        $contract = $this->contract('2026-10', ['unit_price' => 100000]);
        ContractOccupant::factory()->for($contract)->count(3)->create(['move_out_date' => '2026-10-02']);

        $item = $this->invoice($contract, '2026-10')->items->firstWhere('type', 'per_person');

        $this->assertSame('9677.00', $item->amount);
        $this->assertSame(3, $item->metadata['person_days']);
    }

    public function test_clamps_to_tenancy_start_and_excludes_checkout_day_without_rent_after_checkout(): void
    {
        $contract = $this->contract('2026-10');
        $contract->update(['start_date' => '2026-10-10', 'status' => 'terminated', 'terminated_at' => '2026-10-20']);
        ContractOccupant::factory()->for($contract)->create(['move_in_date' => '2026-09-01']);

        $invoice = $this->invoice($contract, '2026-10');

        $this->assertSame('29032.00', $invoice->items->firstWhere('type', 'per_person')->amount);
        $this->assertNull($invoice->items->firstWhere('type', 'rent'));
    }

    public function test_uses_planned_end_as_last_tenancy_day_for_expired_contract(): void
    {
        $contract = $this->contract('2026-09');
        $contract->update(['end_date' => '2026-09-15', 'status' => 'expired']);
        ContractOccupant::factory()->for($contract)->create();

        $item = $this->invoice($contract, '2026-09')->items->firstWhere('type', 'per_person');

        $this->assertSame('45000.00', $item->amount);
        $this->assertSame(15, $item->metadata['person_days']);
    }

    public function test_missing_history_rejects_invoice_even_if_count_is_not_supplied(): void
    {
        $contract = $this->contract('2026-10');
        try {
            $this->invoice($contract, '2026-10');
            $this->fail('Missing history was billed.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('occupancy.missing_occupants')], $exception->errors()['billing_period']);
        }
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
    }

    public function test_same_day_only_stay_has_zero_charge(): void
    {
        $contract = $this->contract('2026-10');
        ContractOccupant::factory()->for($contract)->create(['move_in_date' => '2026-10-10', 'move_out_date' => '2026-10-10']);

        $item = $this->invoice($contract, '2026-10')->items->firstWhere('type', 'per_person');

        $this->assertSame('0.00', $item->amount);
        $this->assertSame(0, $item->metadata['person_days']);
    }

    public function test_cancel_correct_and_replace_preserves_original_occupancy_snapshot(): void
    {
        $contract = $this->contract('2026-10');
        $occupant = ContractOccupant::factory()->for($contract)->create(['full_name' => 'Resident One']);
        $original = $this->invoice($contract, '2026-10');
        $snapshot = $original->items->firstWhere('type', 'per_person')->toArray();
        app(CancelInvoiceAction::class)->handle($original->landlord, $original);
        app(SaveContractOccupantAction::class)->handle($original->landlord, $contract, [
            'full_name' => $occupant->full_name, 'move_in_date' => '2026-10-17', 'move_out_date' => null,
        ], $occupant);

        $replacement = app(GenerateInvoiceAction::class)->handle($original->landlord, [
            'contract_id' => $contract->id, 'billing_period' => '2026-11',
            'replaces_invoice_id' => $original->id, 'replacement_reason' => 'Correct arrival date',
        ]);

        $this->assertSame('3043548.00', $replacement->total);
        $this->assertSame($snapshot, $original->fresh()->items->firstWhere('type', 'per_person')->toArray());
    }

    public function test_occupancy_change_invalidates_preview_before_issue(): void
    {
        $contract = $this->contract('2026-10');
        $occupant = ContractOccupant::factory()->for($contract)->create();
        $action = app(GenerateInvoiceAction::class);
        $data = ['contract_id' => $contract->id, 'billing_period' => '2026-11'];
        $preview = $action->preview($contract->room->property->landlord, $data);
        app(SaveContractOccupantAction::class)->handle($contract->room->property->landlord, $contract, [
            'full_name' => $occupant->full_name, 'move_in_date' => '2026-10-17',
        ], $occupant);

        try {
            $action->handle($contract->room->property->landlord, $data, $preview['fingerprint']);
            $this->fail('Stale preview was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('workflow.preview_changed')], $exception->errors()['billing_period']);
        }
        $this->assertDatabaseCount('invoices', 0);
    }

    private function contract(string $period, array $prices = []): Contract
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00'));
        $month = CarbonImmutable::createFromFormat('!Y-m', $period);
        $contract = Contract::factory()->active()->create(['start_date' => $month, 'end_date' => '2027-10-31', 'rent_amount' => 3000000]);
        $contract->room->update(['max_occupants' => 4]);
        $service = Service::factory()->for($contract->room->property->landlord, 'landlord')->create(['billing_method' => BillingMethod::PerPerson]);
        PropertyService::factory()->for($contract->room->property)->for($service)->create(['unit_price' => 90000, 'effective_from' => $month, ...$prices]);

        return $contract;
    }

    private function invoice(Contract $contract, string $period): Invoice
    {
        $collectionPeriod = CarbonImmutable::createFromFormat('!Y-m', $period)->addMonth()->format('Y-m');

        return app(GenerateInvoiceAction::class)->handle($contract->room->property->landlord, ['contract_id' => $contract->id, 'billing_period' => $collectionPeriod]);
    }
}
