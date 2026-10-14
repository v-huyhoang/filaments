<?php

namespace App\Models;

use App\Enums\ContractStatus;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'room_id', 'tenant_id', 'room_reservation_id', 'start_date', 'end_date',
        'rent_amount', 'deposit_amount', 'payment_due_day', 'status', 'signed_at',
        'terminated_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rent_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'status' => ContractStatus::class,
            'signed_at' => 'datetime',
            'terminated_at' => 'datetime',
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

    public function roomReservation(): BelongsTo
    {
        return $this->belongsTo(RoomReservation::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function scopeCommitted(Builder $query): Builder
    {
        return $query->whereIn('status', [ContractStatus::Scheduled, ContractStatus::Active]);
    }
}
