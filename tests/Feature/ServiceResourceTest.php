<?php

namespace Tests\Feature;

use App\Filament\Resources\Properties\Pages\EditProperty;
use App\Filament\Resources\Properties\RelationManagers\PropertyServicesRelationManager;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\ServiceResource;
use App\Models\Property;
use App\Models\PropertyService;
use App\Models\Service;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_and_record_binding_exclude_foreign_services(): void
    {
        $owned = Service::factory()->create();
        $foreign = Service::factory()->create();
        $this->actingAs($owned->landlord);
        $this->assertSame([$owned->id], ServiceResource::getEloquentQuery()->pluck('id')->all());
        $this->assertNull(ServiceResource::getRecordRouteBindingEloquentQuery()->find($foreign->id));
        config(['app.env' => 'local']);
        $this->get(ServiceResource::getUrl('edit', ['record' => $foreign]))->assertNotFound();
    }

    public function test_create_page_persists_owned_service_through_action(): void
    {
        $landlord = User::factory()->create();
        $this->actingAs($landlord);
        Livewire::test(CreateService::class)->fillForm([
            'name' => 'Electricity', 'code' => 'electricity', 'billing_method' => 'metered', 'unit' => 'kWh', 'is_active' => true,
        ])->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('services', ['landlord_id' => $landlord->id, 'code' => 'electricity']);
    }

    public function test_property_relation_manager_configures_service(): void
    {
        $property = Property::factory()->create();
        $service = Service::factory()->for($property->landlord, 'landlord')->create();
        $this->actingAs($property->landlord);
        Livewire::test(PropertyServicesRelationManager::class, ['ownerRecord' => $property, 'pageClass' => EditProperty::class])
            ->callAction(TestAction::make('configureService')->table(), [
                'service_id' => $service->id, 'billing_method' => 'metered', 'unit_price' => '3500',
                'is_enabled' => true, 'effective_from' => today()->toDateString(),
            ])->assertHasNoActionErrors();
        $this->assertDatabaseHas('property_services', ['property_id' => $property->id, 'service_id' => $service->id, 'unit_price' => 3500]);
    }

    public function test_property_relation_manager_revises_price_without_overwriting_history(): void
    {
        $configuration = PropertyService::factory()->create();
        $this->actingAs($configuration->property->landlord);
        Livewire::test(PropertyServicesRelationManager::class, ['ownerRecord' => $configuration->property, 'pageClass' => EditProperty::class])
            ->callAction(TestAction::make('revisePrice')->table($configuration), [
                'unit_price' => '4000', 'is_enabled' => true, 'effective_from' => today()->addDay()->toDateString(),
            ])->assertHasNoActionErrors();
        $this->assertSame('3500.00', $configuration->fresh()->unit_price);
        $this->assertDatabaseHas('property_services', ['property_id' => $configuration->property_id, 'service_id' => $configuration->service_id, 'unit_price' => 4000]);
    }
}
