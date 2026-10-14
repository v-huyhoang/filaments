<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PropertyServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;

class PropertyService extends Model
{
    /** @use HasFactory<PropertyServiceFactory> */
    use HasFactory;

    protected $fillable = ['service_id', 'unit_price', 'fixed_price', 'is_enabled', 'effective_from'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'fixed_price' => 'decimal:2',
            'is_enabled' => 'boolean',
            'effective_from' => 'immutable_date',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeForLandlord(Builder $query, User $landlord): Builder
    {
        return $query
            ->whereHas('property', fn (Builder $query) => $query->where('landlord_id', $landlord->id))
            ->whereHas('service', fn (Builder $query) => $query->where('landlord_id', $landlord->id));
    }

    public function scopeLatestVersion(Builder $query, ?CarbonInterface $date = null): Builder
    {
        return $query
            ->when($date, fn (Builder $query) => $query->whereDate('effective_from', '<=', $date->toDateString()))
            ->whereNotExists(function (QueryBuilder $newer) use ($date): void {
                $newer->selectRaw('1')->from('property_services as newer')
                    ->whereColumn('newer.property_id', 'property_services.property_id')
                    ->whereColumn('newer.service_id', 'property_services.service_id')
                    ->whereColumn('newer.effective_from', '>', 'property_services.effective_from')
                    ->when($date, fn (QueryBuilder $query) => $query->whereDate('newer.effective_from', '<=', $date->toDateString()));
            });
    }

    public function scopeBillingCandidates(Builder $query, User $landlord, ?CarbonInterface $date = null): Builder
    {
        return $query->forLandlord($landlord)
            ->latestVersion($date ?? today())
            ->where('is_enabled', true)
            ->whereHas('service', fn (Builder $query) => $query->activeOn($date ?? today()))
            ->with('service');
    }
}
