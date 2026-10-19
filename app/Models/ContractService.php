<?php

namespace App\Models;

use App\Enums\BillingMethod;
use Database\Factories\ContractServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractService extends Model
{
    /** @use HasFactory<ContractServiceFactory> */
    use HasFactory;

    protected $fillable = ['service_id', 'use_default', 'billing_method', 'metered_unit', 'unit_price', 'fixed_price', 'is_enabled', 'effective_from'];

    protected function casts(): array
    {
        return ['use_default' => 'boolean', 'billing_method' => BillingMethod::class, 'unit_price' => 'decimal:2', 'fixed_price' => 'decimal:2', 'is_enabled' => 'boolean', 'effective_from' => 'immutable_date'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
