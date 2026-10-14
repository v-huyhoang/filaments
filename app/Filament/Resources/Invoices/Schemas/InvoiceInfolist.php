<?php

namespace App\Filament\Resources\Invoices\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')->label(fn (): string => __('invoices.fields.number')),
                TextEntry::make('billing_period')->label(fn (): string => __('invoices.fields.billing_period')),
                TextEntry::make('revision')->label(fn (): string => __('workflow.revision')),
                TextEntry::make('replaces_invoice_id')->label(fn (): string => __('workflow.replaces_number')),
                TextEntry::make('replacement_reason')->label(fn (): string => __('workflow.reason')),
                TextEntry::make('status')->label(fn (): string => __('invoices.fields.status'))->badge(),
                TextEntry::make('snapshot.property_name')->label(fn (): string => __('invoices.fields.property')),
                TextEntry::make('snapshot.room_number')->label(fn (): string => __('invoices.fields.room')),
                TextEntry::make('snapshot.tenant_name')->label(fn (): string => __('invoices.fields.tenant')),
                TextEntry::make('issued_at')->label(fn (): string => __('invoices.fields.issued_at'))->dateTime(),
                TextEntry::make('due_date')->label(fn (): string => __('invoices.fields.due_date'))->date(),
                RepeatableEntry::make('items')->label(fn (): string => __('invoices.fields.items'))->schema([
                    TextEntry::make('name')->label(fn (): string => __('invoices.fields.name')),
                    TextEntry::make('quantity')->label(fn (): string => __('invoices.fields.quantity')),
                    TextEntry::make('unit')->label(fn (): string => __('invoices.fields.unit')),
                    TextEntry::make('unit_price')->label(fn (): string => __('invoices.fields.unit_price'))->money('VND', decimalPlaces: 2),
                    TextEntry::make('amount')->label(fn (): string => __('invoices.fields.amount'))->money('VND', decimalPlaces: 2),
                ])->columns(5)->columnSpanFull(),
                TextEntry::make('subtotal')->label(fn (): string => __('invoices.fields.subtotal'))->money('VND', decimalPlaces: 2),
                TextEntry::make('total')->label(fn (): string => __('invoices.fields.total'))->money('VND', decimalPlaces: 2),
                TextEntry::make('notes')->label(fn (): string => __('invoices.fields.notes')),
            ]);
    }
}
