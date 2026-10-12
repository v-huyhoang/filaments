<?php

namespace Tests\Feature;

use App\Filament\Resources\Tenants\Pages\CreateTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TenantFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_a_duplicate_identity_number_for_the_same_landlord(): void
    {
        $landlord = User::factory()->create();
        Tenant::factory()->for($landlord, 'landlord')->create([
            'identity_number' => '079123456789',
        ]);

        $this->actingAs($landlord);

        Livewire::test(CreateTenant::class)
            ->fillForm($this->tenantData('079123456789'))
            ->call('create')
            ->assertHasFormErrors(['identity_number' => 'unique']);

        $this->assertDatabaseCount('tenants', 1);
    }

    public function test_it_allows_the_same_identity_number_for_a_different_landlord(): void
    {
        $landlord = User::factory()->create();
        $anotherLandlord = User::factory()->create();
        Tenant::factory()->for($landlord, 'landlord')->create([
            'identity_number' => '079123456789',
        ]);

        $this->actingAs($anotherLandlord);

        Livewire::test(CreateTenant::class)
            ->fillForm($this->tenantData('079123456789'))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tenants', [
            'landlord_id' => $anotherLandlord->id,
            'identity_number' => '079123456789',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function tenantData(string $identityNumber): array
    {
        return [
            'full_name' => 'Nguyen Van An',
            'phone' => '0901 234 567',
            'identity_number' => $identityNumber,
            'status' => 'active',
        ];
    }
}
