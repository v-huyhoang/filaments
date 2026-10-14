<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'meter_reading_id' => null,
            'type' => 'rent',
            'name' => 'Rent',
            'quantity' => '1.000',
            'unit' => 'month',
            'unit_price' => '3000000.00',
            'amount' => '3000000.00',
            'metadata' => [],
        ];
    }
}
