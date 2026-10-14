<?php

namespace App\Models;

use Database\Factories\MeterReadingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class MeterReading extends Model
{
    /** @use HasFactory<MeterReadingFactory> */
    use HasFactory;

    protected $fillable = ['room_id', 'service_id', 'billing_period', 'previous_value', 'current_value', 'usage', 'recorded_at', 'recorded_by', 'notes'];

    protected function casts(): array
    {
        return ['previous_value' => 'decimal:3', 'current_value' => 'decimal:3', 'usage' => 'decimal:3', 'recorded_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (MeterReading $reading) => throw ValidationException::withMessages(['current_value' => __('meter_readings.errors.immutable')]));
        static::deleting(fn (MeterReading $reading) => throw ValidationException::withMessages(['current_value' => __('meter_readings.errors.immutable')]));
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeForLandlord(Builder $query, User $landlord): Builder
    {
        return $query
            ->whereHas('room.property', fn (Builder $query) => $query->where('landlord_id', $landlord->id))
            ->whereHas('service', fn (Builder $query) => $query->where('landlord_id', $landlord->id));
    }
}
