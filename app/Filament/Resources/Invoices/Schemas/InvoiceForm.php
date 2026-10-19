<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Enums\ContractStatus;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Models\Contract;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('contract_id')->label(fn (): string => __('invoices.fields.contract'))
                    ->relationship('contract', 'id', fn (Builder $query) => $query->withoutTrashed()->whereIn('status', [ContractStatus::Active, ContractStatus::Terminated, ContractStatus::Expired])
                        ->whereHas('room.property', fn (Builder $query) => $query->where('landlord_id', auth()->id()))
                        ->whereHas('tenant', fn (Builder $query) => $query->where('landlord_id', auth()->id()))
                        ->with(['room.property', 'tenant']))
                    ->getOptionLabelFromRecordUsing(fn (Contract $record): string => '#'.$record->id.' / '.$record->room->property->name.' / '.$record->room->room_number.' / '.$record->tenant->full_name)
                    ->searchable()->getSearchResultsUsing(fn (string $search): array => self::contractOptions($search))->required(),
                TextInput::make('billing_period')->label(fn (): string => __('invoices.fields.billing_period'))
                    ->type('month')->default(today()->format('Y-m'))->placeholder('YYYY-MM')->rule('date_format:Y-m')->required(),
                TextEntry::make('occupancy_help')->hiddenLabel()->state(fn (): string => __('occupancy.invoice_help')),
                Textarea::make('notes')->label(fn (): string => __('invoices.fields.notes'))->maxLength(5000)
                    ->helperText(fn (): string => __('invoices.generation_help')),
                Hidden::make('replaces_invoice_id'),
                Textarea::make('replacement_reason')->label(fn (): string => __('workflow.reason'))->maxLength(5000)
                    ->visible(fn (Get $get): bool => filled($get('replaces_invoice_id')))->required(fn (Get $get): bool => filled($get('replaces_invoice_id'))),
                Section::make(fn (): string => __('workflow.preview_invoice'))->columnSpanFull()
                    ->visible(fn (Component $livewire): bool => $livewire instanceof CreateInvoice && $livewire->invoicePreview !== null)->schema([
                        TextEntry::make('preview_policy')->label(fn (): string => __('workflow.billing_policy'))->state(fn (): string => __('invoices.generation_help')),
                        TextEntry::make('preview_tenant')->label(fn (): string => __('invoices.fields.tenant'))->state(fn (Component $livewire): ?string => $livewire->invoicePreview['attributes']['snapshot']['tenant_name'] ?? null),
                        TextEntry::make('preview_due')->label(fn (): string => __('invoices.fields.due_date'))->state(fn (Component $livewire): ?string => $livewire->invoicePreview['attributes']['due_date'] ?? null),
                        TextEntry::make('preview_service_period')->label(fn (): string => __('invoices.fields.service_billing_period'))->state(fn (Component $livewire): ?string => $livewire->invoicePreview['attributes']['snapshot']['service_billing_period'] ?? null),
                        ViewEntry::make('preview_items')->label(fn (): string => __('invoices.fields.items'))->state(fn (Component $livewire): array => $livewire->invoicePreview['items'] ?? [])
                            ->view('filament.invoices.items')->columnSpanFull(),
                        TextEntry::make('preview_total')->label(fn (): string => __('invoices.fields.total'))->state(fn (Component $livewire): ?string => $livewire->invoicePreview['attributes']['total'] ?? null)->money('VND', decimalPlaces: 2)->weight('bold')->size('lg')->columnSpanFull(),
                    ]),
            ]);
    }

    /** @return array<int, string> */
    public static function contractOptions(string $search): array
    {
        return Contract::query()->whereIn('status', [ContractStatus::Active, ContractStatus::Terminated, ContractStatus::Expired])
            ->whereHas('room.property', fn (Builder $query) => $query->where('landlord_id', auth()->id()))
            ->whereHas('tenant', fn (Builder $query) => $query->where('landlord_id', auth()->id()))
            ->where(fn (Builder $query) => $query->where('id', 'like', '%'.$search.'%')
                ->orWhereHas('tenant', fn (Builder $query) => $query->where('full_name', 'like', '%'.$search.'%'))
                ->orWhereHas('room', fn (Builder $query) => $query->where('room_number', 'like', '%'.$search.'%'))
                ->orWhereHas('room.property', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%')))
            ->with(['room.property', 'tenant'])->orderBy('id')->limit(50)->get()
            ->mapWithKeys(fn (Contract $record): array => [$record->id => '#'.$record->id.' / '.$record->room->property->name.' / '.$record->room->room_number.' / '.$record->tenant->full_name])->all();
    }
}
