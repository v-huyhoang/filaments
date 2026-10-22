<?php

namespace Tests\Feature;

use App\Actions\Invoices\GenerateInvoiceAction;
use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Enums\RoomStatus;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\DemoRentalSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use LogicException;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DemoRentalSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeder_creates_connected_rental_scenarios(): void
    {
        $this->travelTo('2026-10-22 10:00:00');

        $this->seed();

        $landlord = User::query()->where('email', 'demo-rental@example.test')->sole();
        $this->assertTrue(Hash::check('password', $landlord->password));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('properties', 1);
        $this->assertDatabaseCount('rooms', 5);
        $this->assertDatabaseCount('tenants', 4);
        $this->assertDatabaseCount('contracts', 4);
        $this->assertDatabaseCount('contract_occupants', 3);
        $this->assertDatabaseCount('services', 4);
        $this->assertDatabaseCount('property_services', 4);
        $this->assertDatabaseCount('meter_readings', 8);
        $this->assertDatabaseCount('invoices', 4);
        $this->assertDatabaseCount('invoice_items', 16);
        $this->assertSame(2, Contract::query()->where('status', ContractStatus::Active)->count());
        $this->assertDatabaseHas('contracts', ['room_id' => Room::query()->where('room_number', 'D-103')->value('id'), 'status' => 'draft']);
        $this->assertDatabaseHas('contracts', ['room_id' => Room::query()->where('room_number', 'D-104')->value('id'), 'status' => 'scheduled']);
        $this->assertDatabaseHas('rooms', ['room_number' => 'D-101', 'status' => RoomStatus::OCCUPIED->value]);
        $this->assertDatabaseHas('rooms', ['room_number' => 'D-105', 'status' => RoomStatus::AVAILABLE->value]);
        $this->assertSame(3, Invoice::query()->where('status', InvoiceStatus::Issued)->count());
        $this->assertSame(1, Invoice::query()->where('status', InvoiceStatus::Cancelled)->count());
        $this->assertSame('3530000.00', Invoice::query()->where('billing_period', '2026-10')->sole()->total);
        $this->assertSame([$landlord->id], Invoice::query()->distinct()->pluck('landlord_id')->all());
        $this->assertDatabaseMissing('meter_readings', ['billing_period' => '2026-10']);
    }

    public function test_unbilled_demo_room_is_ready_to_generate_current_invoice(): void
    {
        $this->travelTo('2026-10-22 10:00:00');
        $this->seed(DemoRentalSeeder::class);
        $room = Room::query()->where('room_number', 'D-102')->sole();
        $contract = $room->contracts()->sole();

        $invoice = app(GenerateInvoiceAction::class)->handle($room->property->landlord, [
            'contract_id' => $contract->id, 'billing_period' => '2026-10',
        ]);

        $this->assertSame('3515000.00', $invoice->total);
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame(5, $invoice->items()->count());
    }

    public function test_rerun_preserves_user_changes_and_does_not_duplicate_demo_data(): void
    {
        $this->travelTo('2026-10-22 10:00:00');
        $this->seed(DemoRentalSeeder::class);
        $room = Room::query()->where('room_number', 'D-105')->sole();
        $room->update(['base_rent' => 4_500_000]);
        $landlord = $room->property->landlord;
        $room->property->update(['name' => 'Renamed demo property']);
        $landlord->update(['password' => Hash::make('changed-demo-password')]);
        $this->travelTo('2026-11-01 10:00:00');

        $this->seed(DemoRentalSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('properties', 1);
        $this->assertDatabaseCount('contracts', 4);
        $this->assertDatabaseCount('invoices', 4);
        $this->assertDatabaseCount('meter_readings', 8);
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'base_rent' => 4_500_000]);
        $this->assertTrue(Hash::check('changed-demo-password', $landlord->fresh()->password));
    }

    public function test_demo_does_not_change_existing_landlord_data(): void
    {
        $room = Room::factory()->create()->fresh();
        $existingUser = $room->property->landlord;

        $this->seed(DemoRentalSeeder::class);

        $this->assertSame($existingUser->getAttributes(), $existingUser->fresh()->getAttributes());
        $this->assertSame($room->getAttributes(), $room->fresh()->getAttributes());
        $this->assertFalse(Invoice::query()->forLandlord($existingUser)->exists());
        $this->assertSame(1, Property::query()->where('landlord_id', $existingUser->id)->count());
    }

    public function test_failed_invoice_generation_rolls_back_entire_demo(): void
    {
        Gate::before(fn (User $user, string $ability, array $arguments): ?bool => $ability === 'create' && ($arguments[0] ?? null) === Invoice::class ? false : null);

        try {
            $this->seed(DemoRentalSeeder::class);
            $this->fail('Expected invoice authorization to fail.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('This action is unauthorized.', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('properties', 0);
        $this->assertDatabaseCount('contracts', 0);
        $this->assertDatabaseCount('invoices', 0);
    }

    #[TestWith(['production'])]
    #[TestWith(['staging'])]
    public function test_refuses_demo_data_outside_local_or_testing(string $environment): void
    {
        $this->app['env'] = $environment;
        $this->expectException(LogicException::class);

        $this->seed(DemoRentalSeeder::class);
    }
}
