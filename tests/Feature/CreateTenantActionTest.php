<?php

namespace Tests\Feature;

use App\Actions\Tenants\CreateTenantAction;
use App\Actions\Tenants\UpdateTenantAction;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTenantActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_tenant_for_the_provided_landlord_and_normalizes_phone_numbers(): void
    {
        $landlord = User::factory()->create();
        $anotherLandlord = User::factory()->create();

        $tenant = app(CreateTenantAction::class)->handle($landlord, [
            'landlord_id' => $anotherLandlord->id,
            'full_name' => 'Nguyen Van An',
            'phone' => '0901 234 567',
            'emergency_contact_phone' => '0912-345-678',
            'status' => TenantStatus::Active->value,
        ]);

        $this->assertSame($landlord->id, $tenant->landlord_id);
        $this->assertSame('0901234567', $tenant->phone);
        $this->assertSame('0912345678', $tenant->emergency_contact_phone);
    }

    public function test_it_does_not_allow_one_landlord_to_update_another_landlords_tenant(): void
    {
        $landlord = User::factory()->create();
        $anotherLandlord = User::factory()->create();
        $tenant = Tenant::factory()->for($anotherLandlord, 'landlord')->create();

        $this->expectException(AuthorizationException::class);

        app(UpdateTenantAction::class)->handle($landlord, $tenant, [
            'full_name' => 'Changed Name',
        ]);
    }
}
