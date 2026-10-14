<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = ['landlord_id', 'contract_id', 'billing_period', 'issued_at', 'due_date', 'subtotal', 'total', 'status', 'snapshot', 'notes', 'revision', 'replaces_invoice_id', 'replacement_reason'];

    protected function casts(): array
    {
        return ['issued_at' => 'immutable_datetime', 'due_date' => 'immutable_date', 'subtotal' => 'decimal:2', 'total' => 'decimal:2', 'status' => InvoiceStatus::class, 'snapshot' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            if (array_diff(array_keys($invoice->getDirty()), ['status', 'updated_at']) !== []) {
                throw ValidationException::withMessages(['total' => __('invoices.errors.immutable')]);
            }

            if ($invoice->isDirty('status')) {
                $previous = $invoice->getOriginal('status');
                $allowed = ($previous === InvoiceStatus::Draft && $invoice->status === InvoiceStatus::Issued)
                    || (in_array($previous, [InvoiceStatus::Draft, InvoiceStatus::Issued, InvoiceStatus::Overdue], true) && $invoice->status === InvoiceStatus::Cancelled);

                if (! $allowed) {
                    throw ValidationException::withMessages(['status' => __('invoices.errors.invalid_status_transition')]);
                }
            }
        });
        static::deleting(fn (Invoice $invoice) => throw ValidationException::withMessages(['total' => __('invoices.errors.immutable')]));
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('id');
    }

    public function scopeForLandlord(Builder $query, User $landlord): Builder
    {
        return $query->where('landlord_id', $landlord->id);
    }
}
