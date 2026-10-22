<?php

namespace App\Filament\Resources\MeterReadings\Pages;

use App\Filament\Resources\MeterReadings\MeterReadingResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMeterReadings extends ListRecords
{
    protected static string $resource = MeterReadingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recordProperty')->label(__('meter_readings.bulk.title'))
                ->url(MeterReadingResource::getUrl('record-property'))
                ->visible(fn (): bool => MeterReadingResource::canCreate()),
            CreateAction::make()->label(__('meter_readings.bulk.single'))->color('gray'),
        ];
    }
}
