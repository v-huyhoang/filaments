<?php

namespace Tests\Feature;

use App\Actions\Contracts\TerminateContractAction;
use App\Actions\Invoices\CancelInvoiceAction;
use App\Actions\Invoices\GenerateInvoiceAction;
use App\Actions\MeterReadings\CorrectMeterReadingAction;
use App\Actions\MeterReadings\RecordMeterReadingAction;
use App\Actions\Services\UpdateServiceAction;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Schemas\InvoiceForm;
use App\Filament\Resources\MeterReadings\Pages\CreateMeterReading;
use App\Filament\Resources\MeterReadings\Pages\ListMeterReadings;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MeterReading;
use App\Models\PropertyService;
use App\Models\Service;
use App\Models\User;
use App\Services\MeterReadingInput;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BillingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_before_billing_still_allows_final_and_earlier_invoices(): void
    {
        $this->freezeTime();
        $contract = Contract::factory()->active()->create(['start_date' => today()->subMonthsNoOverflow(2)->startOfMonth(), 'rent_amount' => '3000000']);
        $landlord = $contract->room->property->landlord;
        app(TerminateContractAction::class)->handle($landlord, $contract);
        $final = $this->generate($contract);
        $earlier = $this->generate($contract, ['billing_period' => today()->subMonthNoOverflow()->format('Y-m')]);
        $this->assertSame('3000000.00', $final->total);
        $this->assertSame('3000000.00', $earlier->total);
        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_checkout_disallows_billing_future_months_even_when_original_end_date_is_later(): void
    {
        $this->freezeTime();
        $contract = Contract::factory()->active()->create();
        app(TerminateContractAction::class)->handle($contract->room->property->landlord, $contract);
        $this->expectException(ValidationException::class);
        $this->generate($contract, ['billing_period' => today()->addMonthNoOverflow()->format('Y-m')]);
    }

    public function test_expired_contract_can_bill_actual_earlier_tenancy(): void
    {
        $this->freezeTime();
        $contract = Contract::factory()->active()->create(['status' => 'expired', 'start_date' => today()->subMonthsNoOverflow(2), 'end_date' => today()->subMonthNoOverflow()]);
        $invoice = $this->generate($contract, ['billing_period' => today()->subMonthNoOverflow()->format('Y-m')]);
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
    }

    public function test_cancelled_invoice_can_be_explicitly_replaced_with_history_and_reason(): void
    {
        $contract = Contract::factory()->active()->create(['rent_amount' => 3000000]);
        $original = $this->generate($contract);
        app(CancelInvoiceAction::class)->handle($original->landlord, $original);
        $replacement = $this->generate($contract, ['replaces_invoice_id' => $original->id, 'replacement_reason' => 'Correct occupant count']);
        $this->assertSame(2, $replacement->revision);
        $this->assertSame($original->id, $replacement->replaces_invoice_id);
        $this->assertSame('Correct occupant count', $replacement->replacement_reason);
        $this->assertSame(InvoiceStatus::Cancelled, $original->fresh()->status);
        $this->assertSame('3000000.00', $original->fresh()->total);
        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseCount('invoice_items', 2);
    }

    public function test_replacement_requires_reason_and_does_not_create_partial_records(): void
    {
        $contract = Contract::factory()->active()->create();
        $original = $this->generate($contract);
        app(CancelInvoiceAction::class)->handle($original->landlord, $original);
        try {
            $this->generate($contract, ['replaces_invoice_id' => $original->id]);
            $this->fail('Replacement without a reason was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('replacement_reason', $exception->errors());
        }
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_foreign_invoice_cannot_be_used_as_replacement_source(): void
    {
        $contract = Contract::factory()->active()->create();
        $foreign = Invoice::factory()->create(['status' => 'cancelled']);
        $this->expectException(ValidationException::class);
        $this->generate($contract, ['replaces_invoice_id' => $foreign->id, 'replacement_reason' => 'Invalid source']);
    }

    public function test_replacement_cannot_be_replayed_after_another_revision_exists(): void
    {
        $contract = Contract::factory()->active()->create();
        $original = $this->generate($contract);
        app(CancelInvoiceAction::class)->handle($original->landlord, $original);
        $replacement = $this->generate($contract, ['replaces_invoice_id' => $original->id, 'replacement_reason' => 'Correction']);
        app(CancelInvoiceAction::class)->handle($original->landlord, $replacement);
        $this->expectException(ValidationException::class);
        $this->generate($contract, ['replaces_invoice_id' => $original->id, 'replacement_reason' => 'Stale retry']);
    }

    public function test_database_prevents_two_live_invoices_even_with_different_revisions(): void
    {
        $contract = Contract::factory()->active()->create();
        $original = $this->generate($contract);
        $this->expectException(UniqueConstraintViolationException::class);
        Invoice::factory()->for($contract)->create(['landlord_id' => $original->landlord_id, 'billing_period' => $original->billing_period, 'revision' => 2]);
    }

    public function test_preview_never_persists_and_changed_inputs_require_another_review(): void
    {
        $contract = Contract::factory()->active()->create(['rent_amount' => 3000000]);
        $this->actingAs($contract->room->property->landlord);
        $page = Livewire::test(CreateInvoice::class)->fillForm(['contract_id' => $contract->id])->call('create')->assertHasNoFormErrors()->assertSet('invoicePreview.attributes.total', '3000000.00');
        $this->assertDatabaseCount('invoices', 0);
        $contract->update(['rent_amount' => 4000000]);
        $page->call('create')->assertHasNoFormErrors()->assertSet('invoicePreview.attributes.total', '4000000.00');
        $this->assertDatabaseCount('invoices', 0);
        $page->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('invoices', ['contract_id' => $contract->id, 'total' => 4000000, 'status' => 'issued']);
    }

    public function test_action_rejects_changed_sources_after_preview(): void
    {
        $contract = Contract::factory()->active()->create();
        $data = ['contract_id' => $contract->id, 'billing_period' => today()->format('Y-m')];
        $landlord = $contract->room->property->landlord;
        $action = app(GenerateInvoiceAction::class);
        $preview = $action->preview($landlord, $data);
        $contract->update(['rent_amount' => '123.00']);
        try {
            $action->handle($landlord, $data, $preview['fingerprint']);
            $this->fail('Stale preview was issued.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('billing_period', $exception->errors());
        }
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_replacement_page_prefills_cancelled_invoice_and_issues_reviewed_new_total(): void
    {
        $contract = Contract::factory()->active()->create(['rent_amount' => 3000000]);
        $contract->room->update(['max_occupants' => 2]);
        $service = Service::factory()->for($contract->room->property->landlord, 'landlord')->create(['billing_method' => 'per_person']);
        PropertyService::factory()->for($contract->room->property)->for($service)->create(['unit_price' => 100000, 'effective_from' => today()->startOfMonth()]);
        $original = $this->generate($contract, ['occupant_count' => 1]);
        app(CancelInvoiceAction::class)->handle($original->landlord, $original);
        $this->actingAs($original->landlord);
        $page = Livewire::withQueryParams(['replacement' => $original->id])->test(CreateInvoice::class)
            ->assertFormSet(['contract_id' => $contract->id, 'billing_period' => $original->billing_period, 'replaces_invoice_id' => $original->id])
            ->fillForm(['occupant_count' => 2, 'replacement_reason' => 'Actual occupants are two'])
            ->call('create')->assertHasNoFormErrors()->assertSet('invoicePreview.attributes.total', '3200000.00');
        $this->assertDatabaseCount('invoices', 1);
        $page->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('invoices', ['replaces_invoice_id' => $original->id, 'total' => 3200000, 'status' => 'issued']);
        $this->assertSame('3100000.00', $original->fresh()->total);
    }

    public function test_replacement_page_does_not_reveal_foreign_invoice(): void
    {
        $foreign = Invoice::factory()->create(['status' => 'cancelled']);
        $this->actingAs(User::factory()->create());
        Livewire::withQueryParams(['replacement' => $foreign->id])->test(CreateInvoice::class)->assertNotFound();
    }

    public function test_contract_billing_link_prefills_final_month_after_checkout(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 14));
        $contract = Contract::factory()->active()->create(['start_date' => '2026-08-01']);
        app(TerminateContractAction::class)->handle($contract->room->property->landlord, $contract, '2026-09-30');
        $this->actingAs($contract->room->property->landlord);
        Livewire::withQueryParams(['contract' => $contract->id])->test(CreateInvoice::class)
            ->assertFormSet(['contract_id' => $contract->id, 'billing_period' => '2026-09']);
    }

    public function test_client_cannot_change_server_reviewed_preview(): void
    {
        $contract = Contract::factory()->active()->create();
        $this->actingAs($contract->room->property->landlord);
        $page = Livewire::test(CreateInvoice::class)->fillForm(['contract_id' => $contract->id])->call('create')->assertHasNoFormErrors();
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $page->set('invoicePreview.attributes.total', '1.00');
    }

    public function test_invoice_search_uses_tenant_room_and_property_names_without_foreign_records(): void
    {
        $contract = Contract::factory()->active()->create();
        $contract->tenant->update(['full_name' => 'Search Tenant']);
        $contract->room->update(['room_number' => 'Search Room']);
        $contract->room->property->update(['name' => 'Search Property']);
        $foreign = Contract::factory()->active()->create();
        $foreign->tenant->update(['full_name' => 'Search Tenant']);
        $this->actingAs($contract->room->property->landlord);
        foreach (['Search Tenant', 'Search Room', 'Search Property'] as $search) {
            $this->assertSame([$contract->id], array_keys(InvoiceForm::contractOptions($search)));
        }
    }

    public function test_latest_uninvoiced_reading_can_be_corrected_with_exact_usage_and_audit(): void
    {
        $reading = MeterReading::factory()->create(['previous_value' => '100.100', 'current_value' => '125.500', 'usage' => '25.400']);
        $owner = $reading->room->property->landlord;
        $corrected = app(CorrectMeterReadingAction::class)->handle($owner, $reading, ['current_value' => '124.300', 'reason' => 'Typo on meter']);
        $this->assertSame('24.200', $corrected->usage);
        $this->assertDatabaseHas('meter_reading_corrections', ['meter_reading_id' => $reading->id, 'recorded_by' => $owner->id, 'reason' => 'Typo on meter']);
        $audit = DB::table('meter_reading_corrections')->first();
        $this->assertSame('125.500', json_decode($audit->before, true)['current_value']);
        $this->assertSame('124.300', json_decode($audit->after, true)['current_value']);
    }

    public function test_reading_correction_is_available_from_list(): void
    {
        $reading = MeterReading::factory()->create(['previous_value' => 100]);
        $this->actingAs($reading->room->property->landlord);
        Livewire::test(ListMeterReadings::class)->callTableAction('correct', $reading, ['current_value' => 120, 'reason' => 'Meter typo'])->assertHasNoTableActionErrors();
        $this->assertSame('20.000', $reading->fresh()->usage);
    }

    #[TestWith(['draft'])]
    #[TestWith(['issued'])]
    #[TestWith(['partial'])]
    #[TestWith(['paid'])]
    #[TestWith(['overdue'])]
    public function test_reading_referenced_by_uncancelled_invoice_cannot_be_corrected(string $status): void
    {
        $reading = MeterReading::factory()->create();
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->for($invoice)->create(['meter_reading_id' => $reading->id]);
        DB::table('invoices')->where('id', $invoice->id)->update(['status' => $status]);
        $this->expectException(ValidationException::class);
        app(CorrectMeterReadingAction::class)->handle($reading->room->property->landlord, $reading, ['current_value' => 130, 'reason' => 'Cannot alter historical input']);
    }

    public function test_cancel_correct_and_replace_preserves_old_financial_and_meter_snapshots(): void
    {
        $this->freezeTime();
        $contract = Contract::factory()->active()->create(['rent_amount' => 3000000]);
        $configuration = PropertyService::factory()->for($contract->room->property)->create(['effective_from' => today()->startOfMonth(), 'unit_price' => 4000]);
        $reading = MeterReading::factory()->for($contract->room)->for($configuration->service)->create(['previous_value' => 100, 'current_value' => 125, 'usage' => 25]);
        $original = $this->generate($contract);
        app(CancelInvoiceAction::class)->handle($original->landlord, $original);
        app(CorrectMeterReadingAction::class)->handle($original->landlord, $reading, ['current_value' => 120, 'reason' => 'Wrong reading before cancellation']);
        $replacement = $this->generate($contract, ['replaces_invoice_id' => $original->id, 'replacement_reason' => 'Correct meter typo']);
        $this->assertSame('3100000.00', $original->fresh()->total);
        $this->assertSame('25.000', $original->items()->where('type', 'metered')->firstOrFail()->quantity);
        $this->assertSame('125.000', $original->items()->where('type', 'metered')->firstOrFail()->metadata['current_value']);
        $this->assertSame('3080000.00', $replacement->total);
        $this->assertDatabaseCount('meter_reading_corrections', 1);
    }

    public function test_correction_form_displays_domain_validation_and_keeps_audit_empty(): void
    {
        $reading = MeterReading::factory()->create(['previous_value' => 100]);
        $this->actingAs($reading->room->property->landlord);
        Livewire::test(ListMeterReadings::class)->callTableAction('correct', $reading, ['current_value' => 99, 'reason' => 'Invalid correction'])
            ->assertHasTableActionErrors(['current_value']);
        $this->assertDatabaseCount('meter_reading_corrections', 0);
    }

    public function test_correction_history_is_visible_to_owner(): void
    {
        $reading = MeterReading::factory()->create();
        $owner = $reading->room->property->landlord;
        app(CorrectMeterReadingAction::class)->handle($owner, $reading, ['current_value' => 130, 'reason' => 'Visible audit reason']);
        $this->actingAs($owner);
        Livewire::test(ListMeterReadings::class)->mountTableAction('correctionHistory', $reading)->assertMountedActionModalSee('Visible audit reason');
    }

    public function test_reading_with_a_later_period_cannot_be_corrected(): void
    {
        $reading = MeterReading::factory()->create(['billing_period' => '2026-09']);
        MeterReading::factory()->for($reading->room)->for($reading->service)->create(['billing_period' => '2026-10']);
        $this->expectException(ValidationException::class);
        app(CorrectMeterReadingAction::class)->handle($reading->room->property->landlord, $reading, ['current_value' => 130, 'reason' => 'Cannot break chain']);
    }

    #[TestWith(['current_value', '99'])]
    #[TestWith(['current_value', '-1'])]
    #[TestWith(['current_value', '123.4567'])]
    #[TestWith(['reason', ''])]
    public function test_invalid_correction_leaves_reading_and_audit_unchanged(string $field, string $value): void
    {
        $reading = MeterReading::factory()->create(['previous_value' => 100]);
        try {
            app(CorrectMeterReadingAction::class)->handle($reading->room->property->landlord, $reading, ['current_value' => 130, 'reason' => 'Correction', $field => $value]);
            $this->fail('Invalid correction was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertSame('125.500', $reading->fresh()->current_value);
        $this->assertDatabaseCount('meter_reading_corrections', 0);
    }

    public function test_foreign_landlord_cannot_correct_reading(): void
    {
        $reading = MeterReading::factory()->create();
        $this->expectException(AuthorizationException::class);
        app(CorrectMeterReadingAction::class)->handle(User::factory()->create(), $reading, ['current_value' => 130, 'reason' => 'Foreign request']);
    }

    public function test_correction_and_audit_roll_back_together(): void
    {
        $reading = MeterReading::factory()->create();
        $connection = DB::connection();
        $originalDispatcher = $connection->getEventDispatcher();
        $dispatcher = clone $originalDispatcher;
        $dispatcher->listen(QueryExecuted::class, function (QueryExecuted $query): void {
            if (preg_match('/^update ["`]?meter_readings["`]? /i', $query->sql)) {
                throw new \RuntimeException('test failure');
            }
        });
        $connection->setEventDispatcher($dispatcher);
        try {
            app(CorrectMeterReadingAction::class)->handle($reading->room->property->landlord, $reading, ['current_value' => 130, 'reason' => 'Rollback test']);
            $this->fail('Write failure was ignored.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('test failure', $exception->getMessage());
        } finally {
            $connection->setEventDispatcher($originalDispatcher);
        }
        $this->assertDatabaseCount('meter_reading_corrections', 0);
        $this->assertSame('125.500', $reading->fresh()->current_value);
    }

    public function test_missing_month_is_rejected_and_can_be_filled_before_next_reading(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 14));
        $contract = Contract::factory()->active()->create();
        $configuration = PropertyService::factory()->for($contract->room->property)->create(['effective_from' => '2026-08-01']);
        $service = $configuration->service;
        $owner = $contract->room->property->landlord;
        $action = app(RecordMeterReadingAction::class);
        $data = ['room_id' => $contract->room_id, 'service_id' => $service->id, 'billing_period' => '2026-08', 'previous_value' => 100, 'current_value' => 110];
        $action->handle($owner, $data);
        try {
            $action->handle($owner, [...$data, 'billing_period' => '2026-10', 'previous_value' => null, 'current_value' => 130]);
            $this->fail('Missing period was accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(__('workflow.missing_period'), $exception->errors()['billing_period'][0]);
        }
        $action->handle($owner, [...$data, 'billing_period' => '2026-09', 'previous_value' => null, 'current_value' => 120]);
        $last = $action->handle($owner, [...$data, 'billing_period' => '2026-10', 'previous_value' => null, 'current_value' => 130]);
        $this->assertSame('10.000', $last->usage);
        $this->assertDatabaseCount('meter_readings', 3);
    }

    public function test_meter_form_filters_services_and_loads_previous_reading(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 14));
        $contract = Contract::factory()->active()->create();
        $configuration = PropertyService::factory()->for($contract->room->property)->create(['effective_from' => '2026-09-01']);
        $reading = MeterReading::factory()->for($contract->room)->for($configuration->service)->create(['billing_period' => '2026-09', 'current_value' => '110.500']);
        $owner = $contract->room->property->landlord;
        $foreign = Service::factory()->create();
        $input = app(MeterReadingInput::class);
        $this->assertSame([$configuration->service_id => $configuration->service->name], $input->services($owner, $contract->room_id, '2026-10'));
        $this->assertSame([], $input->services(User::factory()->create(), $contract->room_id, '2026-10'));
        $this->assertSame([], $input->services($owner, $contract->room_id, 'invalid'));
        $this->actingAs($owner);
        Livewire::test(CreateMeterReading::class)->fillForm(['room_id' => $contract->room_id])
            ->fillForm(['service_id' => $configuration->service_id])->assertFormSet(['previous_value' => '110.500']);
    }

    public function test_service_deactivation_and_reactivation_preserve_historical_billing(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 15));
        $contract = Contract::factory()->active()->create(['start_date' => '2026-08-01']);
        $configuration = PropertyService::factory()->for($contract->room->property)->create(['effective_from' => '2026-08-01']);
        $service = $configuration->service;
        $owner = $contract->room->property->landlord;
        $attributes = ['name' => $service->name, 'code' => $service->code, 'billing_method' => $service->billing_method->value, 'unit' => $service->unit];
        app(UpdateServiceAction::class)->handle($owner, $service, [...$attributes, 'is_active' => false]);
        $this->travelTo(now()->setDate(2026, 10, 14));
        app(UpdateServiceAction::class)->handle($owner, $service->fresh(), [...$attributes, 'is_active' => true]);
        MeterReading::factory()->for($contract->room)->for($service)->create(['billing_period' => '2026-08', 'usage' => 25]);
        $this->assertSame(2, $this->generate($contract, ['billing_period' => '2026-08'])->items->count());
        $this->assertSame(1, $this->generate($contract, ['billing_period' => '2026-09'])->items->count());
        $this->assertSame([$service->id => $service->name], app(MeterReadingInput::class)->services($owner, $contract->room_id, '2026-10'));
    }

    /** @param array<string, mixed> $overrides */
    private function generate(Contract $contract, array $overrides = []): Invoice
    {
        return app(GenerateInvoiceAction::class)->handle($contract->room->property->landlord, [
            'contract_id' => $contract->id, 'billing_period' => today()->format('Y-m'), ...$overrides,
        ]);
    }
}
