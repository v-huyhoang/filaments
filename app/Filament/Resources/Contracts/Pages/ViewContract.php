<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewContract extends ViewRecord
{
    protected static string $resource = ContractResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('room.property.name')->label(fn (): string => __('tables.property')),
            TextEntry::make('room.room_number')->label(fn (): string => __('tables.room')),
            TextEntry::make('tenant.full_name')->label(fn (): string => __('tables.tenant')),
            TextEntry::make('status')->label(fn (): string => __('tables.status'))->badge(),
            TextEntry::make('start_date')->label(fn (): string => __('tables.start_date'))->date(),
            TextEntry::make('end_date')->label(fn (): string => __('tables.end_date'))->date(),
            TextEntry::make('rent_amount')->label(fn (): string => __('forms.rent_amount'))->money('VND'),
            TextEntry::make('occupancy_policy')->hiddenLabel()->state(fn (): string => __('occupancy.history_help')),
        ]);
    }
}
