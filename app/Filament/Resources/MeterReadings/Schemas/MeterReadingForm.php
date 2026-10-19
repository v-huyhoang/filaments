<?php

namespace App\Filament\Resources\MeterReadings\Schemas;

use App\Models\Room;
use App\Services\MeterReadingInput;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class MeterReadingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::components());
    }

    /** @return array<int, Component> */
    public static function components(?int $roomId = null, ?string $defaultPeriod = null): array
    {
        return [
            $roomId !== null ? Hidden::make('room_id')->default($roomId) : Select::make('room_id')->label(fn (): string => __('meter_readings.fields.room'))
                ->relationship('room', 'room_number', fn (Builder $query) => $query->whereHas('property', fn (Builder $query) => $query->where('landlord_id', auth()->id()))->with('property'))
                ->getOptionLabelFromRecordUsing(fn (Room $record): string => $record->property->name.' / '.$record->room_number)
                ->searchable()->required()->live()->afterStateUpdated(function (Set $set): void {
                    $set('service_id', null);
                    $set('previous_value', null);
                    $set('current_value', null);
                }),
            Select::make('service_id')->label(fn (): string => __('meter_readings.fields.service'))
                ->options(fn (Get $get): array => app(MeterReadingInput::class)->services(auth()->user(), $get('room_id') ? (int) $get('room_id') : null, $get('billing_period')))
                ->searchable()->required()->live()->afterStateUpdated(function (Get $get, Set $set): void {
                    $set('previous_value', app(MeterReadingInput::class)->previous(auth()->user(), $get('room_id') ? (int) $get('room_id') : null, $get('service_id') ? (int) $get('service_id') : null, $get('billing_period')));
                    $set('current_value', null);
                }),
            TextInput::make('billing_period')->label(fn (): string => __('meter_readings.fields.billing_period'))
                ->type('month')->default($defaultPeriod ?? today()->format('Y-m'))->placeholder('YYYY-MM')->rule('date_format:Y-m')->required()->live()
                ->afterStateUpdated(function (Set $set, ?string $state, ?string $old): void {
                    if ($state === $old) {
                        return;
                    }
                    $set('service_id', null);
                    $set('previous_value', null);
                    $set('current_value', null);
                }),
            TextInput::make('previous_value')->label(fn (): string => __('meter_readings.fields.previous_value'))
                ->numeric()->minValue(0)->maxValue(999999999.999)->rule('decimal:0,3')
                ->helperText(fn (): string => __('meter_readings.previous_help')),
            TextInput::make('current_value')->label(fn (): string => __('meter_readings.fields.current_value'))
                ->numeric()->minValue(0)->maxValue(999999999.999)->rule('decimal:0,3')->required(),
            Textarea::make('notes')->label(fn (): string => __('meter_readings.fields.notes'))->maxLength(5000)
                ->helperText(fn (): string => __('meter_readings.immutable_help')),
        ];
    }
}
