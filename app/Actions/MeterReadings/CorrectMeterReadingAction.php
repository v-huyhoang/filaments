<?php

namespace App\Actions\MeterReadings;

use App\Enums\InvoiceStatus;
use App\Models\MeterReading;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CorrectMeterReadingAction
{
    /** @param array{current_value: numeric, reason: string} $attributes */
    public function handle(User $landlord, MeterReading $reading, array $attributes): MeterReading
    {
        Gate::forUser($landlord)->authorize('correct', $reading);
        $validated = Validator::make($attributes, [
            'current_value' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
            'reason' => ['required', 'string', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($landlord, $reading, $validated): MeterReading {
            $reading = MeterReading::query()->forLandlord($landlord)->findOrFail($reading->id);
            Property::query()->where('landlord_id', $landlord->id)->whereKey($reading->room->property_id)->lockForUpdate()->firstOrFail();
            Room::query()->whereKey($reading->room_id)->lockForUpdate()->firstOrFail();
            $reading = MeterReading::query()->forLandlord($landlord)->lockForUpdate()->findOrFail($reading->id);
            if (DB::table('invoice_items')->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                ->where('meter_reading_id', $reading->id)->where('invoices.status', '!=', InvoiceStatus::Cancelled->value)->lockForUpdate()->first(['invoice_items.id']) !== null ||
                MeterReading::query()->where('room_id', $reading->room_id)->where('service_id', $reading->service_id)->where('billing_period', '>', $reading->billing_period)->lockForUpdate()->first(['id']) !== null) {
                throw ValidationException::withMessages(['current_value' => __('workflow.reading_locked')]);
            }

            $current = BigDecimal::of((string) $validated['current_value'])->toScale(3);
            if ($current->isLessThan($reading->previous_value)) {
                throw ValidationException::withMessages(['current_value' => __('meter_readings.errors.decreasing_value')]);
            }
            $before = ['current_value' => $reading->current_value, 'usage' => $reading->usage];
            $after = ['current_value' => (string) $current, 'usage' => (string) $current->minus($reading->previous_value)->toScale(3)];
            DB::table('meter_reading_corrections')->insert([
                'meter_reading_id' => $reading->id, 'before' => json_encode($before, JSON_THROW_ON_ERROR),
                'after' => json_encode($after, JSON_THROW_ON_ERROR), 'reason' => $validated['reason'],
                'recorded_by' => $landlord->id, 'created_at' => now(),
            ]);
            DB::table('meter_readings')->where('id', $reading->id)->update([...$after, 'updated_at' => now()]);

            return $reading->refresh();
        }, attempts: 3);
    }
}
