<?php

namespace App\Models;

use App\Enums\BillingMethod;
use Carbon\CarbonInterface;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $fillable = ['name', 'code', 'billing_method', 'unit', 'is_active'];

    protected function casts(): array
    {
        return ['billing_method' => BillingMethod::class, 'is_active' => 'boolean'];
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function propertyServices(): HasMany
    {
        return $this->hasMany(PropertyService::class);
    }

    public function scopeActiveOn(Builder $query, CarbonInterface $date): Builder
    {
        return $query->where(function (Builder $query) use ($date): void {
            $query->whereRaw('(select history.is_active from service_status_history as history where history.service_id = services.id and history.effective_from <= ? order by history.effective_from desc limit 1) = 1', [$date->toDateString()])
                ->orWhere(function (Builder $query): void {
                    $query->where('is_active', true)->whereNotExists(fn (QueryBuilder $history) => $history->selectRaw('1')->from('service_status_history')->whereColumn('service_status_history.service_id', 'services.id'));
                });
        });
    }
}
