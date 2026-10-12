<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'full_name',
        'phone',
        'email',
        'date_of_birth',
        'identity_number',
        'identity_issued_date',
        'identity_issued_place',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'identity_issued_date' => 'date',
            'status' => TenantStatus::class,
        ];
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function roomReservations(): HasMany
    {
        return $this->hasMany(RoomReservation::class);
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: static fn (?string $value): ?string => blank($value) ? null : preg_replace('/\D+/', '', $value),
        );
    }

    protected function emergencyContactPhone(): Attribute
    {
        return Attribute::make(
            set: static fn (?string $value): ?string => blank($value) ? null : preg_replace('/\D+/', '', $value),
        );
    }

    protected function identityNumber(): Attribute
    {
        return Attribute::make(
            set: static fn (?string $value): ?string => blank($value) ? null : preg_replace('/\D+/', '', $value),
        );
    }
}
