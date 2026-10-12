<?php

namespace App\Models;

use App\Enums\RoomReservationStatus;
use Database\Factories\RoomReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomReservation extends Model
{
    /** @use HasFactory<RoomReservationFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'room_id', 'tenant_id', 'expected_move_in_date', 'hold_amount', 'paid_at',
        'payment_method', 'payment_reference', 'expires_at', 'status', 'cancelled_at',
        'refunded_at', 'refunded_amount', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_move_in_date' => 'date',
            'hold_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'status' => RoomReservationStatus::class,
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'refunded_amount' => 'decimal:2',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    public function scopeLiveHold(Builder $query): Builder
    {
        return $query->where('status', RoomReservationStatus::Held)->where('expires_at', '>', now());
    }
}
