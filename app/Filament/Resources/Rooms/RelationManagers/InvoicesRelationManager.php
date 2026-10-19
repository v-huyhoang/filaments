<?php

namespace App\Filament\Resources\Rooms\RelationManagers;

use App\Enums\ContractStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Invoices\Tables\InvoicesTable;
use App\Models\Contract;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public function booted(): void
    {
        Gate::authorize('view', $this->getOwnerRecord());
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('invoices.plural_label');
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
        return InvoicesTable::configure($table)
            ->description(fn (): string => __('resources.room.invoices_help'))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->forLandlord(auth()->user()))
            ->recordUrl(fn (Invoice $record): string => InvoiceResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()->url(fn (Invoice $record): string => InvoiceResource::getUrl('view', ['record' => $record])),
            ])
            ->headerActions([
                Action::make('createInvoice')->label(fn (): string => __('resources.room.create_invoice'))
                    ->visible(fn (): bool => ! $this->getOwnerRecord()->trashed() && Gate::allows('create', Invoice::class))
                    ->schema([
                        Select::make('contract_id')->label(fn (): string => __('invoices.fields.contract'))
                            ->options(fn (): array => $this->contractOptions())
                            ->default(fn (): ?int => array_key_first($this->contractOptions()))->required()->searchable(),
                    ])
                    ->action(function (array $data): void {
                        Gate::authorize('view', $this->getOwnerRecord());
                        Gate::authorize('create', Invoice::class);
                        abort_if($this->getOwnerRecord()->trashed(), 403);
                        $contract = $this->contractsQuery()->findOrFail($data['contract_id']);
                        Gate::authorize('view', $contract);
                        $this->redirect(InvoiceResource::getUrl('create', ['contract' => $contract->id]));
                    }),
            ]);
    }

    private function contractsQuery(): Builder
    {
        return Contract::query()->where('room_id', $this->getOwnerRecord()->id)
            ->whereHas('room.property', fn (Builder $query) => $query->where('landlord_id', auth()->id()))
            ->whereHas('tenant', fn (Builder $query) => $query->where('landlord_id', auth()->id()))
            ->whereIn('status', [ContractStatus::Active, ContractStatus::Terminated, ContractStatus::Expired]);
    }

    /** @return array<int, string> */
    private function contractOptions(): array
    {
        return $this->contractsQuery()->with('tenant')->orderByDesc('start_date')->orderByDesc('id')->get()
            ->mapWithKeys(fn (Contract $contract): array => [$contract->id => '#'.$contract->id.' / '.$contract->tenant->full_name.' / '.$contract->start_date->format('d/m/Y')])->all();
    }
}
