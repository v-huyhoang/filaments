<?php

namespace Tests\Feature;

use App\Actions\Services\CreateServiceAction;
use App\Actions\Services\UpdateServiceAction;
use App\Models\PropertyService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ServiceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_uses_server_controlled_owner_and_defaults(): void
    {
        $landlord = User::factory()->create();
        $service = app(CreateServiceAction::class)->handle($landlord, [
            ...$this->data(), 'landlord_id' => User::factory()->create()->id,
        ]);
        $this->assertSame($landlord->id, $service->landlord_id);
        $this->assertTrue($service->is_active);
        $this->assertDatabaseHas('services', ['id' => $service->id, 'code' => 'electricity']);
    }

    #[TestWith(['code', 'INVALID CODE'])]
    #[TestWith(['billing_method', 'unknown'])]
    #[TestWith(['name', ''])]
    #[TestWith(['unit', ''])]
    public function test_invalid_definition_is_rejected(string $field, string $value): void
    {
        $this->expectException(ValidationException::class);
        app(CreateServiceAction::class)->handle(User::factory()->create(), [...$this->data(), $field => $value]);
    }

    public function test_code_is_unique_per_landlord_but_reusable_by_other_landlords(): void
    {
        $landlord = User::factory()->create();
        app(CreateServiceAction::class)->handle($landlord, $this->data());
        app(CreateServiceAction::class)->handle(User::factory()->create(), $this->data());
        $this->assertDatabaseCount('services', 2);
        $this->expectException(ValidationException::class);
        app(CreateServiceAction::class)->handle($landlord, $this->data());
    }

    public function test_owner_can_update_without_changing_code(): void
    {
        $service = Service::factory()->create(['code' => 'electricity']);
        app(UpdateServiceAction::class)->handle($service->landlord, $service, [...$this->data(), 'name' => 'New name', 'is_active' => false]);
        $this->assertSame('New name', $service->fresh()->name);
        $this->assertFalse($service->fresh()->is_active);
    }

    public function test_another_landlord_cannot_update_service(): void
    {
        $this->expectException(AuthorizationException::class);
        app(UpdateServiceAction::class)->handle(User::factory()->create(), Service::factory()->create(), $this->data());
    }

    #[TestWith(['billing_method', 'fixed'])]
    #[TestWith(['unit', 'm3'])]
    public function test_used_definition_cannot_change_billing_semantics(string $field, string $value): void
    {
        $configuration = PropertyService::factory()->create();
        $service = $configuration->service;
        $this->expectException(ValidationException::class);
        app(UpdateServiceAction::class)->handle($service->landlord, $service, [
            ...$this->data(), 'code' => $service->code, $field => $value,
        ]);
    }

    private function data(): array
    {
        return ['name' => 'Electricity', 'code' => 'electricity', 'billing_method' => 'metered', 'unit' => 'kWh'];
    }
}
