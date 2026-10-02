<?php

namespace Tests\Feature;

use App\Actions\Properties\CreatePropertyAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatePropertyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_property_for_the_provided_landlord(): void
    {
        $landlord = User::factory()->create();

        $property = app(CreatePropertyAction::class)->handle($landlord, [
            'name' => 'Nhà trọ HongBui',
            'address' => '42/9/6 Hà Thị Khiêm',
            'description' => 'Khu nhà trọ gần chợ',
        ]);

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'landlord_id' => $landlord->id,
            'name' => 'Nhà trọ HongBui',
        ]);
    }

    public function test_it_does_not_allow_input_to_override_the_property_owner(): void
    {
        $landlord = User::factory()->create();
        $anotherLandlord = User::factory()->create();

        $property = app(CreatePropertyAction::class)->handle($landlord, [
            'landlord_id' => $anotherLandlord->id,
            'name' => 'Nhà trọ HongBui',
            'address' => '42/9/6 Hà Thị Khiêm',
        ]);

        $this->assertSame($landlord->id, $property->landlord_id);
    }

    public function test_a_property_is_active_by_default_and_can_be_soft_deleted(): void
    {
        $landlord = User::factory()->create();

        $property = app(CreatePropertyAction::class)->handle($landlord, [
            'name' => 'Nhà trọ HongBui',
            'address' => '42/9/6 Hà Thị Khiêm',
        ]);

        $this->assertTrue($property->is_active);

        $property->delete();

        $this->assertSoftDeleted('properties', [
            'id' => $property->id,
        ]);
    }
}
