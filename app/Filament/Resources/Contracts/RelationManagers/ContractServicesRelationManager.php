<?php

namespace App\Filament\Resources\Contracts\RelationManagers;

use App\Actions\Contracts\ConfigureContractServiceAction;
use App\Enums\BillingMethod;
use App\Models\Service;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ContractServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'contractServices';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('occupancy.contract_services');
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
        return $table->description(fn (): string => __('occupancy.default_help'))
            ->modifyQueryUsing(fn (Builder $query) => $query->with('service'))->defaultSort('effective_from', 'desc')
            ->columns([
                TextColumn::make('service.name')->label(fn (): string => __('services.label')),
                IconColumn::make('use_default')->label(fn (): string => __('occupancy.use_default'))->boolean(),
                TextColumn::make('billing_method')->label(fn (): string => __('services.fields.billing_method'))->badge(),
                TextColumn::make('unit_price')->label(fn (): string => __('services.fields.unit_price'))->money('VND'),
                TextColumn::make('fixed_price')->label(fn (): string => __('services.fields.fixed_price'))->money('VND'),
                IconColumn::make('is_enabled')->label(fn (): string => __('services.fields.is_enabled'))->boolean(),
                TextColumn::make('effective_from')->label(fn (): string => __('services.fields.effective_from'))->date(),
            ])->headerActions([
                Action::make('configureService')->label(fn (): string => __('occupancy.configure'))
                    ->visible(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))->schema([
                        Select::make('service_id')->label(fn (): string => __('services.label'))->required()->searchable()
                            ->options(fn (): array => Service::query()->where('landlord_id', auth()->id())->where('is_active', true)
                                ->whereHas('propertyServices', fn (Builder $query) => $query->where('property_id', $this->getOwnerRecord()->room->property_id))->pluck('name', 'id')->all()),
                        Toggle::make('use_default')->label(fn (): string => __('occupancy.use_default'))->default(false)->live()->required(),
                        Select::make('billing_method')->label(fn (): string => __('services.fields.billing_method'))->options(BillingMethod::class)->live()
                            ->visible(fn (Get $get): bool => ! $get('use_default'))->required(fn (Get $get): bool => ! $get('use_default')),
                        TextInput::make('metered_unit')->label(fn (): string => __('services.fields.unit'))->maxLength(50)
                            ->visible(fn (Get $get): bool => ! $get('use_default') && in_array($get('billing_method'), ['metered', BillingMethod::Metered], true))
                            ->required(fn (Get $get): bool => ! $get('use_default') && in_array($get('billing_method'), ['metered', BillingMethod::Metered], true)),
                        TextInput::make('unit_price')->label(fn (): string => __('services.fields.unit_price'))->numeric()->minValue(0)->maxValue(9999999999.99)->rule('decimal:0,2')
                            ->visible(fn (Get $get): bool => ! $get('use_default') && in_array($get('billing_method'), ['metered', 'per_person', BillingMethod::Metered, BillingMethod::PerPerson], true))->required(fn (Get $get): bool => ! $get('use_default') && in_array($get('billing_method'), ['metered', 'per_person', BillingMethod::Metered, BillingMethod::PerPerson], true)),
                        TextInput::make('fixed_price')->label(fn (): string => __('services.fields.fixed_price'))->numeric()->minValue(0)->maxValue(9999999999.99)->rule('decimal:0,2')
                            ->visible(fn (Get $get): bool => ! $get('use_default') && in_array($get('billing_method'), ['fixed', BillingMethod::Fixed], true))->required(fn (Get $get): bool => ! $get('use_default') && in_array($get('billing_method'), ['fixed', BillingMethod::Fixed], true)),
                        Toggle::make('is_enabled')->label(fn (): string => __('services.fields.is_enabled'))->default(true)->required()->visible(fn (Get $get): bool => ! $get('use_default')),
                        DatePicker::make('effective_from')->label(fn (): string => __('services.fields.effective_from'))->default(today()->startOfMonth())
                            ->minDate(today()->startOfMonth())->required()->helperText(fn (): string => __('occupancy.effective_help')),
                    ])->action(function (array $data, Schema $schema): void {
                        try {
                            app(ConfigureContractServiceAction::class)->handle(auth()->user(), $this->getOwnerRecord(), $data);
                        } catch (ValidationException $exception) {
                            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $errors, string $field): array => [$schema->getStatePath().'.'.$field => $errors])->all());
                        }
                    }),
            ]);
    }
}
