<?php

namespace App\Filament\Resources\Rooms\RelationManagers;

use App\Actions\MeterReadings\RecordMeterReadingAction;
use App\Filament\Resources\MeterReadings\Schemas\MeterReadingForm;
use App\Filament\Resources\MeterReadings\Tables\MeterReadingsTable;
use App\Models\MeterReading;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MeterReadingsRelationManager extends RelationManager
{
    protected static string $relationship = 'meterReadings';

    public function booted(): void
    {
        Gate::authorize('view', $this->getOwnerRecord());
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources.room.meter_readings');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return MeterReadingsTable::configure($table)
            ->description(fn (): string => __('resources.room.readings_help'))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->forLandlord(auth()->user())->with(['room.property', 'service', 'recorder']))
            ->headerActions([
                Action::make('recordReading')->label(fn (): string => __('resources.room.record_reading'))
                    ->visible(fn (): bool => ! $this->getOwnerRecord()->trashed() && Gate::allows('create', MeterReading::class))
                    ->schema(fn (): array => MeterReadingForm::components($this->getOwnerRecord()->id, today()->startOfMonth()->subMonth()->format('Y-m')))
                    ->action(function (array $data, Schema $schema): void {
                        Gate::authorize('view', $this->getOwnerRecord());
                        abort_if($this->getOwnerRecord()->trashed(), 403);
                        try {
                            app(RecordMeterReadingAction::class)->handle(auth()->user(), [...$data, 'room_id' => $this->getOwnerRecord()->id]);
                        } catch (ValidationException $exception) {
                            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $errors, string $field): array => [$schema->getStatePath().'.'.$field => $errors])->all());
                        }
                    }),
            ]);
    }
}
