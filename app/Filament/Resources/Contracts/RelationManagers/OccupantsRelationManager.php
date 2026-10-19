<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use App\Actions\Contracts\SaveContractOccupantAction;
use App\Models\ContractOccupant;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OccupantsRelationManager extends RelationManager
{
    protected static string $relationship = 'occupants';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('occupancy.occupants');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        $schema = fn (): array => [
            TextInput::make('full_name')->label(fn (): string => __('occupancy.full_name'))->required()->maxLength(255),
            DatePicker::make('move_in_date')->label(fn (): string => __('occupancy.move_in_date'))->required()->default(today()),
            DatePicker::make('move_out_date')->label(fn (): string => __('occupancy.move_out_date'))
                ->afterOrEqual('move_in_date')->helperText(fn (): string => __('occupancy.date_help')),
        ];

        return $table->description(fn (): string => __('occupancy.history_help'))->columns([
            TextColumn::make('full_name')->label(fn (): string => __('occupancy.full_name'))->searchable(),
            TextColumn::make('move_in_date')->label(fn (): string => __('occupancy.move_in_date'))->date()->sortable(),
            TextColumn::make('move_out_date')->label(fn (): string => __('occupancy.move_out_date'))->date()->sortable(),
        ])->headerActions([
            Action::make('addOccupant')->label(fn (): string => __('occupancy.add'))
                ->visible(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))->schema($schema)
                ->action(fn (array $data, Schema $schema) => $this->save($data, $schema)),
        ])->recordActions([
            Action::make('editOccupant')->label(fn (): string => __('occupancy.edit'))
                ->visible(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))->schema($schema)
                ->fillForm(fn (ContractOccupant $record): array => $record->only(['full_name', 'move_in_date', 'move_out_date']))
                ->action(fn (ContractOccupant $record, array $data, Schema $schema) => $this->save($data, $schema, $record)),
        ]);
    }

    private function save(array $data, Schema $schema, ?ContractOccupant $record = null): void
    {
        try {
            app(SaveContractOccupantAction::class)->handle(auth()->user(), $this->getOwnerRecord(), $data, $record);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $errors, string $field): array => [$schema->getStatePath().'.'.$field => $errors])->all());
        }
    }
}
