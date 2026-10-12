<?php

namespace Tests\Feature;

use App\Filament\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_lists_tenants_belonging_to_the_authenticated_landlord(): void
    {
        $landlord = User::factory()->create();
        $anotherLandlord = User::factory()->create();
        $ownedTenant = Tenant::factory()->for($landlord, 'landlord')->create();
        $anotherLandlordTenant = Tenant::factory()->for($anotherLandlord, 'landlord')->create();

        $this->actingAs($landlord);

        $tenantIds = TenantResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($ownedTenant->id, $tenantIds);
        $this->assertNotContains($anotherLandlordTenant->id, $tenantIds);
    }

    public function test_it_cannot_resolve_another_landlords_tenant_from_the_record_route_query(): void
    {
        $landlord = User::factory()->create();
        $anotherLandlord = User::factory()->create();
        $anotherLandlordTenant = Tenant::factory()->for($anotherLandlord, 'landlord')->create();

        $this->actingAs($landlord);

        $tenant = TenantResource::getRecordRouteBindingEloquentQuery()
            ->find($anotherLandlordTenant->id);

        $this->assertNull($tenant);
    }
}
