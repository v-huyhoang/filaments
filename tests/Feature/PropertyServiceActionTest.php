<?php

namespace Tests\Feature;

use App\Actions\Services\ConfigurePropertyServiceAction;
use App\Actions\Services\UpdatePropertyServiceAction;
use App\Models\Property;
use App\Models\PropertyService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PropertyServiceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owned_property_can_configure_service(): void
    {
        $property = Property::factory()->create();
        $service = Service::factory()->for($property->landlord, 'landlord')->create();
        $configuration = app(ConfigurePropertyServiceAction::class)->handle($property->landlord, $property, [
            'service_id' => $service->id, 'unit_price' => '3500.50', 'effective_from' => today()->toDateString(),
        ]);
        $this->assertDatabaseHas('property_services', ['id' => $configuration->id, 'property_id' => $property->id, 'service_id' => $service->id]);
        $this->assertSame('3500.50', $configuration->fresh()->unit_price);
    }

    public function test_duplicate_configuration_is_rejected(): void
    {
        $configuration = PropertyService::factory()->create();
        $this->expectException(ValidationException::class);
        app(ConfigurePropertyServiceAction::class)->handle($configuration->property->landlord, $configuration->property, [
            'service_id' => $configuration->service_id, 'unit_price' => 1, 'effective_from' => today()->addDay()->toDateString(),
        ]);
    }

    public function test_foreign_property_is_forbidden(): void
    {
        $this->expectException(AuthorizationException::class);
        app(ConfigurePropertyServiceAction::class)->handle(User::factory()->create(), Property::factory()->create(), []);
    }

    public function test_foreign_service_is_rejected_without_writing_configuration(): void
    {
        $property = Property::factory()->create();
        try {
            app(ConfigurePropertyServiceAction::class)->handle($property->landlord, $property, [
                'service_id' => Service::factory()->create()->id, 'unit_price' => 1, 'effective_from' => today()->toDateString(),
            ]);
            $this->fail('Foreign service was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('service_id', $exception->errors());
        }
        $this->assertDatabaseCount('property_services', 0);
    }

    public function test_inactive_service_cannot_be_configured(): void
    {
        $property = Property::factory()->create();
        $service = Service::factory()->for($property->landlord, 'landlord')->inactive()->create();
        $this->expectException(ValidationException::class);
        app(ConfigurePropertyServiceAction::class)->handle($property->landlord, $property, ['service_id' => $service->id]);
    }

    public function test_price_revision_preserves_history_and_takes_effect_on_selected_date(): void
    {
        $original = PropertyService::factory()->create();
        $revision = app(UpdatePropertyServiceAction::class)->handle($original->property->landlord, $original, [
            'unit_price' => '4000', 'effective_from' => today()->addDay()->toDateString(),
        ]);
        $this->assertSame('3500.00', $original->fresh()->unit_price);
        $this->assertSame('4000.00', $revision->fresh()->unit_price);
        $this->assertSame([$original->id], PropertyService::query()->billingCandidates($original->property->landlord, today())->pluck('id')->all());
        $this->assertSame([$revision->id], PropertyService::query()->billingCandidates($original->property->landlord, today()->addDay())->pluck('id')->all());
    }

    public function test_disabled_revision_does_not_fall_back_to_enabled_history(): void
    {
        $original = PropertyService::factory()->create();
        app(UpdatePropertyServiceAction::class)->handle($original->property->landlord, $original, [
            'unit_price' => 3500, 'is_enabled' => false, 'effective_from' => today()->addDay()->toDateString(),
        ]);
        $this->assertCount(0, PropertyService::query()->billingCandidates($original->property->landlord, today()->addDay())->get());
    }

    public function test_stale_version_cannot_be_revised(): void
    {
        $original = PropertyService::factory()->create();
        $action = app(UpdatePropertyServiceAction::class);
        $action->handle($original->property->landlord, $original, ['unit_price' => 4000, 'effective_from' => today()->addDay()->toDateString()]);
        $this->expectException(ValidationException::class);
        $action->handle($original->property->landlord, $original, ['unit_price' => 5000, 'effective_from' => today()->addDays(2)->toDateString()]);
    }

    public function test_revision_must_have_later_effective_date(): void
    {
        $original = PropertyService::factory()->create();
        $this->expectException(ValidationException::class);
        app(UpdatePropertyServiceAction::class)->handle($original->property->landlord, $original, ['unit_price' => 4000, 'effective_from' => today()->toDateString()]);
    }

    public function test_other_landlord_cannot_revise_prices(): void
    {
        $this->expectException(AuthorizationException::class);
        app(UpdatePropertyServiceAction::class)->handle(User::factory()->create(), PropertyService::factory()->create(), []);
    }

    public function test_billing_candidates_exclude_foreign_future_and_inactive_services(): void
    {
        $owned = PropertyService::factory()->create();
        PropertyService::factory()->create();
        PropertyService::factory()->for($owned->property)->create(['effective_from' => today()->addDay()]);
        $inactive = Service::factory()->for($owned->property->landlord, 'landlord')->inactive()->create();
        PropertyService::factory()->for($owned->property)->for($inactive)->create();
        $this->assertSame([$owned->id], PropertyService::query()->billingCandidates($owned->property->landlord)->pluck('id')->all());
    }

    public function test_invalid_revision_rolls_back_without_changing_history(): void
    {
        $original = PropertyService::factory()->create();
        try {
            app(UpdatePropertyServiceAction::class)->handle($original->property->landlord, $original, ['unit_price' => -1, 'effective_from' => today()->addDay()->toDateString()]);
            $this->fail('Negative price was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('unit_price', $exception->errors());
        }
        $this->assertDatabaseCount('property_services', 1);
        $this->assertSame('3500.00', $original->fresh()->unit_price);
    }

    public function test_database_rejects_duplicate_version(): void
    {
        $original = PropertyService::factory()->create();
        $this->expectException(UniqueConstraintViolationException::class);
        PropertyService::factory()->for($original->property)->for($original->service)->create(['effective_from' => $original->effective_from]);
    }

    public function test_inactive_service_cannot_be_reenabled_by_revision(): void
    {
        $original = PropertyService::factory()->create();
        $original->service->update(['is_active' => false]);
        $this->expectException(ValidationException::class);
        app(UpdatePropertyServiceAction::class)->handle($original->property->landlord, $original, ['unit_price' => 3500, 'is_enabled' => true, 'effective_from' => today()->addDay()->toDateString()]);
    }
}
