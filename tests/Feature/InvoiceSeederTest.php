<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\PropertyService;
use Database\Seeders\InvoiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class InvoiceSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_billable_contract_and_skips_duplicate(): void
    {
        Contract::factory()->active()->create();
        $this->seed(InvoiceSeeder::class);
        $this->seed(InvoiceSeeder::class);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_items', 1);
        $this->assertDatabaseHas('invoices', ['status' => 'issued']);
    }

    public function test_skips_contract_with_missing_reading(): void
    {
        $contract = Contract::factory()->active()->create();
        PropertyService::factory()->for($contract->room->property)->create();
        $this->seed(InvoiceSeeder::class);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_items', 0);
    }

    public function test_refuses_demo_invoices_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(LogicException::class);
        $this->seed(InvoiceSeeder::class);
    }
}
