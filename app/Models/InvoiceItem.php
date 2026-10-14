<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory;

    protected $fillable = ['invoice_id', 'meter_reading_id', 'type', 'name', 'quantity', 'unit', 'unit_price', 'amount', 'metadata'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (InvoiceItem $item): void {
            if ($item->invoice()->lockForUpdate()->value('status') !== InvoiceStatus::Draft) {
                throw ValidationException::withMessages(['total' => __('invoices.errors.immutable')]);
            }
        });
        static::updating(fn (InvoiceItem $item) => throw ValidationException::withMessages(['total' => __('invoices.errors.immutable')]));
        static::deleting(fn (InvoiceItem $item) => throw ValidationException::withMessages(['total' => __('invoices.errors.immutable')]));
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function meterReading(): BelongsTo
    {
        return $this->belongsTo(MeterReading::class);
    }
}
