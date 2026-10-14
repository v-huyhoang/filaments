<?php

namespace Database\Seeders;

use App\Actions\MeterReadings\RecordMeterReadingAction;
use App\Enums\BillingMethod;
use App\Models\MeterReading;
use App\Models\PropertyService;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use LogicException;

class MeterReadingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(RecordMeterReadingAction $recordReading): void
    {
        if (app()->isProduction()) {
            throw new LogicException('Demo meter readings must not be seeded in production.');
        }

        $period = today()->format('Y-m');

        User::query()->each(function (User $landlord) use ($period, $recordReading): void {
            PropertyService::query()->billingCandidates($landlord, today()->endOfMonth())
                ->whereHas('service', fn (Builder $query) => $query->where('billing_method', BillingMethod::Metered))
                ->with('property.rooms')->each(function (PropertyService $configuration) use ($landlord, $period, $recordReading): void {
                    foreach ($configuration->property->rooms as $room) {
                        $latest = MeterReading::query()->where('room_id', $room->id)->where('service_id', $configuration->service_id)
                            ->orderByDesc('billing_period')->first();

                        if ($latest !== null && $latest->billing_period >= $period) {
                            continue;
                        }

                        $previous = $latest?->current_value ?? '100.000';
                        $recordReading->handle($landlord, [
                            'room_id' => $room->id,
                            'service_id' => $configuration->service_id,
                            'billing_period' => $period,
                            'previous_value' => $previous,
                            'current_value' => (string) BigDecimal::of($previous)->plus('25.000'),
                            'notes' => 'Demo meter reading created by MeterReadingSeeder.',
                        ]);
                    }
                });
        });
    }
}
