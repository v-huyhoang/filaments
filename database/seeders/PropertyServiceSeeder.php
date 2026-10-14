<?php

namespace Database\Seeders;

use App\Actions\Services\ConfigurePropertyServiceAction;
use App\Enums\BillingMethod;
use App\Models\Property;
use App\Models\Service;
use Illuminate\Database\Seeder;

class PropertyServiceSeeder extends Seeder
{
    public function run(): void
    {
        Property::query()->with('landlord')->each(function (Property $property): void {
            $property->landlord->services()->where('is_active', true)->each(function (Service $service) use ($property): void {
                if ($property->propertyServices()->where('service_id', $service->id)->exists()) {
                    return;
                }

                $prices = match ($service->billing_method) {
                    BillingMethod::Metered, BillingMethod::PerPerson => ['unit_price' => 3500],
                    BillingMethod::Fixed => ['fixed_price' => 100000],
                    BillingMethod::Manual => [],
                };

                app(ConfigurePropertyServiceAction::class)->handle($property->landlord, $property, [
                    'service_id' => $service->id, 'effective_from' => today()->toDateString(), ...$prices,
                ]);
            });
        });
    }
}
