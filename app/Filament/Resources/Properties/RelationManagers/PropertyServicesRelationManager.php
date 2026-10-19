<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use App\Actions\Services\ConfigurePropertyServiceAction;
use App\Actions\Services\UpdatePropertyServiceAction;
use App\Enums\BillingMethod;
use App\Filament\Resources\Properties\Schemas\PropertyServiceForm;
use App\Models\PropertyService;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class PropertyServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'propertyServices';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('services.property_services');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->forLandlord($this->landlord())->latestVersion()->with(['service', 'property']))
            ->modelLabel(fn (): string => __('services.configuration'))
            ->columns([
                TextColumn::make('service.name')->label(fn (): string => __('services.label'))->searchable(),
                TextColumn::make('service.billing_method')->label(fn (): string => __('services.fields.billing_method'))->badge(),
                TextColumn::make('service.unit')->label(fn (): string => __('services.fields.unit')),
                TextColumn::make('unit_price')->label(fn (): string => __('services.fields.unit_price'))->money('VND'),
                TextColumn::make('fixed_price')->label(fn (): string => __('services.fields.fixed_price'))->money('VND'),
                IconColumn::make('is_enabled')->label(fn (): string => __('services.fields.is_enabled'))->boolean(),
                TextColumn::make('effective_from')->label(fn (): string => __('services.fields.effective_from'))->date()->sortable(),
            ])
            ->headerActions([
                Action::make('configureService')->label(fn (): string => __('services.actions.configure'))
                    ->visible(fn (): bool => Gate::allows('update', $this->getOwnerRecord()) && Gate::allows('create', PropertyService::class))
                    ->schema(PropertyServiceForm::components())
                    ->action(fn (array $data) => app(ConfigurePropertyServiceAction::class)->handle($this->landlord(), $this->getOwnerRecord(), $data)),
            ])
            ->recordActions([
                Action::make('revisePrice')->label(fn (): string => __('services.actions.revise'))
                    ->visible(fn (PropertyService $record): bool => Gate::allows('update', $record))
                    ->fillForm(fn (PropertyService $record): array => [
                        'billing_method' => $record->service->billing_method->value,
                        'unit_price' => $record->unit_price,
                        'fixed_price' => $record->fixed_price,
                        'is_enabled' => $record->is_enabled,
                        'effective_from' => $record->service->billing_method === BillingMethod::PerPerson
                            ? $record->effective_from->max(today())->addMonthNoOverflow()->startOfMonth()->toDateString()
                            : $record->effective_from->max(today())->addDay()->toDateString(),
                    ])
                    ->schema(fn (PropertyService $record): array => PropertyServiceForm::components($record->service))
                    ->action(fn (PropertyService $record, array $data) => app(UpdatePropertyServiceAction::class)->handle($this->landlord(), $record, $data)),
            ]);
    }

    private function landlord(): User
    {
        $landlord = auth()->user();
        abort_unless($landlord instanceof User, 403);

        return $landlord;
    }
}
