<?php

namespace Database\Seeders;

use App\Enums\BillingMethod;
use App\Models\User;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['electricity', 'Electricity', BillingMethod::Metered, 'kWh'],
            ['water', 'Water', BillingMethod::Metered, 'm3'],
            ['internet', 'Internet', BillingMethod::Fixed, 'month'],
            ['parking', 'Parking', BillingMethod::Fixed, 'month'],
            ['trash', 'Trash collection', BillingMethod::PerPerson, 'person'],
            ['other', 'Other', BillingMethod::Manual, 'entry'],
        ];

        User::query()->each(function (User $landlord) use ($services): void {
            foreach ($services as [$code, $name, $method, $unit]) {
                $landlord->services()->firstOrCreate(['code' => $code], [
                    'name' => $name, 'billing_method' => $method, 'unit' => $unit, 'is_active' => true,
                ]);
            }
        });
    }
}
