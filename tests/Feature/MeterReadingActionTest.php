<?php

namespace Tests\Feature;

use App\Actions\MeterReadings\RecordMeterReadingAction;
use App\Enums\BillingMethod;
use App\Models\MeterReading;
use App\Models\PropertyService;
use App\Models\Room;
use App\Models\Service;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MeterReadingActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_reading_calculates_exact_usage_and_ignores_client_audit_fields(): void
    {
        [$room, $service] = $this->configuredRoom();
        $this->freezeTime();
        $reading = $this->record($room, $service, ['recorded_by' => User::factory()->create()->id, 'recorded_at' => '2000-01-01', 'usage' => 999]);
        $this->assertSame('0.200', $reading->usage);
        $this->assertSame($room->property->landlord_id, $reading->recorded_by);
        $this->assertSame(now()->toDateTimeString(), $reading->recorded_at->toDateTimeString());
        $this->assertDatabaseHas('meter_readings', ['id' => $reading->id, 'usage' => '0.200']);
    }

    public function test_later_period_uses_latest_prior_current_value(): void
    {
        [$room, $service] = $this->configuredRoom();
        $first = $this->record($room, $service);
        $next = $this->record($room, $service, ['billing_period' => today()->addMonthNoOverflow()->format('Y-m'), 'previous_value' => null, 'current_value' => '2.301']);
        $this->assertSame($first->current_value, $next->previous_value);
        $this->assertSame('2.001', $next->usage);
    }

    public function test_zero_consumption_is_valid(): void
    {
        [$room, $service] = $this->configuredRoom();
        $this->assertSame('0.000', $this->record($room, $service, ['current_value' => '0.1'])->usage);
    }

    #[TestWith(['current_value', '-1'])]
    #[TestWith(['current_value', '0.099'])]
    #[TestWith(['current_value', '1.0001'])]
    #[TestWith(['current_value', '1000000000'])]
    #[TestWith(['previous_value', '-1'])]
    #[TestWith(['previous_value', null])]
    #[TestWith(['billing_period', '2026-13'])]
    #[TestWith(['billing_period', '2026-01-01'])]
    public function test_invalid_reading_is_rejected_without_writing(string $field, mixed $value): void
    {
        [$room, $service] = $this->configuredRoom();
        try {
            $this->record($room, $service, [$field => $value]);
            $this->fail('Invalid reading was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertDatabaseCount('meter_readings', 0);
    }

    public function test_duplicate_period_is_rejected_by_action(): void
    {
        [$room, $service] = $this->configuredRoom();
        $this->record($room, $service);
        $this->expectException(ValidationException::class);
        $this->record($room, $service);
    }

    public function test_duplicate_period_is_rejected_by_database(): void
    {
        $reading = MeterReading::factory()->create();
        $this->expectException(UniqueConstraintViolationException::class);
        MeterReading::factory()->for($reading->room)->for($reading->service)->create(['billing_period' => $reading->billing_period]);
    }

    public function test_backfill_cannot_break_existing_reading_chain(): void
    {
        [$room, $service] = $this->configuredRoom();
        $this->record($room, $service, ['billing_period' => today()->addMonthNoOverflow()->format('Y-m')]);
        $this->expectException(ValidationException::class);
        $this->record($room, $service);
    }

    public function test_client_cannot_override_previous_reading(): void
    {
        [$room, $service] = $this->configuredRoom();
        $this->record($room, $service);
        $this->expectException(ValidationException::class);
        $this->record($room, $service, ['billing_period' => today()->addMonthNoOverflow()->format('Y-m'), 'previous_value' => 0, 'current_value' => 1]);
    }

    #[TestWith(['fixed'])]
    #[TestWith(['per_person'])]
    #[TestWith(['manual'])]
    public function test_non_metered_service_is_rejected(string $method): void
    {
        [$room, $service] = $this->configuredRoom();
        $service->update(['billing_method' => BillingMethod::from($method)]);
        $this->expectException(ValidationException::class);
        $this->record($room, $service);
    }

    public function test_disabled_service_does_not_fall_back_to_earlier_enabled_configuration(): void
    {
        [$room, $service] = $this->configuredRoom();
        PropertyService::factory()->for($room->property)->for($service)->disabled()->create(['effective_from' => today()->endOfMonth()->toDateString()]);
        $this->expectException(ValidationException::class);
        $this->record($room, $service);
    }

    public function test_inactive_service_is_rejected(): void
    {
        [$room, $service] = $this->configuredRoom();
        $service->update(['is_active' => false]);
        $this->expectException(ValidationException::class);
        $this->record($room, $service);
    }

    public function test_unconfigured_service_is_rejected(): void
    {
        [$room, $service] = $this->configuredRoom();
        $otherService = Service::factory()->for($service->landlord, 'landlord')->create();
        $this->expectException(ValidationException::class);
        $this->record($room, $otherService);
    }

    public function test_future_configuration_cannot_enable_earlier_billing_month(): void
    {
        [$room, $service] = $this->configuredRoom();
        $this->expectException(ValidationException::class);
        $this->record($room, $service, ['billing_period' => today()->subMonthNoOverflow()->format('Y-m')]);
    }

    public function test_cross_landlord_room_is_rejected(): void
    {
        [$room, $service] = $this->configuredRoom();
        $this->expectException(ValidationException::class);
        app(RecordMeterReadingAction::class)->handle(User::factory()->create(), $this->data($room, $service));
    }

    public function test_cross_landlord_service_is_rejected_even_with_inconsistent_configuration(): void
    {
        [$room] = $this->configuredRoom();
        $foreign = Service::factory()->create();
        PropertyService::factory()->for($room->property)->for($foreign)->create(['effective_from' => today()->startOfMonth()]);
        $this->expectException(ValidationException::class);
        $this->record($room, $foreign);
    }

    public function test_deleted_room_cannot_receive_reading(): void
    {
        [$room, $service] = $this->configuredRoom();
        $room->delete();
        $this->expectException(ValidationException::class);
        $this->record($room, $service);
    }

    public function test_deleted_property_cannot_receive_reading(): void
    {
        [$room, $service] = $this->configuredRoom();
        $room->property->delete();
        $this->expectException(ValidationException::class);
        $this->record($room, $service);
    }

    public function test_previous_lookup_does_not_use_other_rooms_or_services(): void
    {
        [$room, $service] = $this->configuredRoom();
        MeterReading::factory()->for($room)->create(['billing_period' => today()->subMonthNoOverflow()->format('Y-m'), 'current_value' => 999]);
        MeterReading::factory()->for($service)->create(['billing_period' => today()->subMonthNoOverflow()->format('Y-m'), 'current_value' => 999]);
        $this->assertSame('0.100', $this->record($room, $service)->previous_value);
    }

    public function test_denied_create_permission_prevents_direct_action(): void
    {
        [$room, $service] = $this->configuredRoom();
        Gate::before(fn (User $user, string $ability): ?bool => $ability === 'create' ? false : null);
        $this->expectException(AuthorizationException::class);
        $this->record($room, $service);
    }

    public function test_policy_only_allows_owner_to_view_and_forbids_mutations(): void
    {
        $reading = MeterReading::factory()->create();
        $owner = $reading->room->property->landlord;
        $this->assertTrue(Gate::forUser($owner)->allows('view', $reading));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('view', $reading));
        $this->assertFalse(Gate::forUser($owner)->allows('update', $reading));
        $this->assertFalse(Gate::forUser($owner)->allows('delete', $reading));
    }

    #[TestWith(['update'])]
    #[TestWith(['delete'])]
    public function test_recorded_reading_cannot_be_mutated_through_model(string $operation): void
    {
        $reading = MeterReading::factory()->create();
        try {
            if ($operation === 'update') {
                $reading->update(['current_value' => 999]);
            } else {
                $reading->delete();
            }
            $this->fail('Immutable reading was changed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('current_value', $exception->errors());
        }
        $this->assertDatabaseHas('meter_readings', ['id' => $reading->id, 'current_value' => '125.500']);
    }

    private function configuredRoom(): array
    {
        $room = Room::factory()->create();
        $configuration = PropertyService::factory()->for($room->property)->create(['effective_from' => today()->startOfMonth()->toDateString()]);

        return [$room, $configuration->service];
    }

    private function record(Room $room, Service $service, array $overrides = []): MeterReading
    {
        return app(RecordMeterReadingAction::class)->handle($room->property->landlord, [...$this->data($room, $service), ...$overrides]);
    }

    private function data(Room $room, Service $service): array
    {
        return ['room_id' => $room->id, 'service_id' => $service->id, 'billing_period' => today()->format('Y-m'), 'previous_value' => '0.1', 'current_value' => '0.3'];
    }
}
