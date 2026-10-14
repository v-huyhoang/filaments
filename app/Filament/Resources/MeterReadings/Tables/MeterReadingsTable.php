<?php

namespace App\Filament\Resources\MeterReadings\Tables;

use App\Actions\MeterReadings\CorrectMeterReadingAction;
use App\Models\MeterReading;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeterReadingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('room.property.name')->label(fn (): string => __('meter_readings.fields.property'))->searchable(),
                TextColumn::make('room.room_number')->label(fn (): string => __('meter_readings.fields.room'))->searchable(),
                TextColumn::make('service.name')->label(fn (): string => __('meter_readings.fields.service'))->searchable(),
                TextColumn::make('billing_period')->label(fn (): string => __('meter_readings.fields.billing_period'))->sortable(),
                TextColumn::make('previous_value')->label(fn (): string => __('meter_readings.fields.previous_value')),
                TextColumn::make('current_value')->label(fn (): string => __('meter_readings.fields.current_value')),
                TextColumn::make('usage')->label(fn (): string => __('meter_readings.fields.usage')),
                TextColumn::make('recorded_at')->label(fn (): string => __('meter_readings.fields.recorded_at'))->dateTime()->sortable(),
                TextColumn::make('recorder.name')->label(fn (): string => __('meter_readings.fields.recorded_by')),
            ])
            ->filters([
                Filter::make('billing_period')->schema([
                    TextInput::make('billing_period')->label(fn (): string => __('meter_readings.fields.billing_period'))->rule('date_format:Y-m'),
                ])->query(fn (Builder $query, array $data): Builder => $query->when($data['billing_period'] ?? null, fn (Builder $query, string $period) => $query->where('billing_period', $period))),
            ])
            ->defaultSort('billing_period', 'desc')
            ->recordActions([
                Action::make('correct')->label(fn (): string => __('workflow.correct_reading'))
                    ->authorize('correct')->schema([
                        TextInput::make('current_value')->label(fn (): string => __('meter_readings.fields.current_value'))->numeric()->required(),
                        Textarea::make('reason')->label(fn (): string => __('workflow.reason'))->required()->maxLength(5000),
                    ])->fillForm(fn (MeterReading $record): array => ['current_value' => $record->current_value])
                    ->action(function (MeterReading $record, array $data, Schema $schema): void {
                        try {
                            app(CorrectMeterReadingAction::class)->handle(auth()->user(), $record, $data);
                        } catch (ValidationException $exception) {
                            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $errors, string $field): array => [$schema->getStatePath().'.'.$field => $errors])->all());
                        }
                    }),
                Action::make('correctionHistory')->label(fn (): string => __('workflow.correction_history'))->authorize('view')->modalSubmitAction(false)->schema([
                    RepeatableEntry::make('corrections')->label(fn (): string => __('workflow.correction_history'))
                        ->state(fn (MeterReading $record): array => DB::table('meter_reading_corrections')->join('users', 'users.id', '=', 'meter_reading_corrections.recorded_by')
                            ->where('meter_reading_id', $record->id)->orderByDesc('meter_reading_corrections.id')->limit(100)
                            ->get(['meter_reading_corrections.before', 'meter_reading_corrections.after', 'reason', 'users.name as actor', 'meter_reading_corrections.created_at'])
                            ->map(fn (object $correction): array => [
                                'before_value' => json_decode($correction->before, true)['current_value'],
                                'after_value' => json_decode($correction->after, true)['current_value'],
                                'reason' => $correction->reason, 'actor' => $correction->actor, 'created_at' => $correction->created_at,
                            ])->all())->schema([
                                TextEntry::make('before_value')->label(fn (): string => __('workflow.before_correction')),
                                TextEntry::make('after_value')->label(fn (): string => __('workflow.after_correction')),
                                TextEntry::make('reason')->label(fn (): string => __('workflow.reason')),
                                TextEntry::make('actor')->label(fn (): string => __('meter_readings.fields.recorded_by')),
                                TextEntry::make('created_at')->label(fn (): string => __('meter_readings.fields.recorded_at'))->dateTime(),
                            ])->columns(2),
                ]),
            ])
            ->toolbarActions([]);
    }
}
