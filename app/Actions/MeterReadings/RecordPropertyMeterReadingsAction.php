<?php

namespace App\Actions\MeterReadings;

use App\Models\MeterReading;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecordPropertyMeterReadingsAction
{
    public function __construct(private RecordMeterReadingAction $recordReading) {}

    /** @param array{property_id: int, billing_period: string, readings: array<string, array{room_id: int, service_id: int, previous_value?: numeric|null, current_value?: numeric|null}>} $attributes */
    public function handle(User $landlord, array $attributes): int
    {
        Gate::forUser($landlord)->authorize('create', MeterReading::class);
        $validated = Validator::make($attributes, [
            'property_id' => ['required', 'integer'],
            'billing_period' => ['required', 'date_format:Y-m'],
            'readings' => ['required', 'array', 'max:2000'],
            'readings.*' => ['required', 'array'],
            'readings.*.room_id' => ['required', 'integer'],
            'readings.*.service_id' => ['required', 'integer'],
            'readings.*.previous_value' => ['nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
            'readings.*.current_value' => ['nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
        ])->validate();

        return DB::transaction(function () use ($landlord, $validated): int {
            $property = Property::query()->where('landlord_id', $landlord->id)->lockForUpdate()->findOrFail($validated['property_id']);
            Gate::forUser($landlord)->authorize('update', $property);
            $roomIds = $property->rooms()->pluck('id')->all();
            $count = 0;
            foreach ($validated['readings'] as $key => $reading) {
                if (! in_array((int) $reading['room_id'], $roomIds, true)) {
                    throw ValidationException::withMessages(['readings.'.$key.'.room_id' => __('meter_readings.errors.room_unavailable')]);
                }
                if (blank($reading['current_value'] ?? null)) {
                    continue;
                }
                try {
                    $this->recordReading->handle($landlord, [
                        'room_id' => $reading['room_id'], 'service_id' => $reading['service_id'],
                        'billing_period' => $validated['billing_period'],
                        'previous_value' => $reading['previous_value'] ?? null, 'current_value' => $reading['current_value'],
                    ]);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(
                        fn (array $errors, string $field): array => ['readings.'.$key.'.'.$field => $errors]
                    )->all());
                }
                $count++;
            }
            if ($count === 0) {
                throw ValidationException::withMessages(['readings' => __('meter_readings.bulk.enter_reading')]);
            }

            return $count;
        }, attempts: 3);
    }
}
