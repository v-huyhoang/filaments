<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Actions\Invoices\CancelInvoiceAction;
use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Rooms\RoomResource;
use App\Models\Invoice;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        $room = $this->getRecord()->loadMissing('contract.room')->contract?->room;
        $roomUrl = $room !== null && Gate::allows('view', $room)
            ? RoomResource::getUrl('view', ['record' => $room, 'relation' => 'invoices']) : null;

        return [
            Action::make('viewRoom')->label(fn (): string => __('resources.room.back_to_room'))
                ->visible($roomUrl !== null)->url($roomUrl),
            Action::make('replaceInvoice')->label(fn (): string => __('workflow.replace_invoice'))
                ->visible(fn (): bool => $this->getRecord()->status === InvoiceStatus::Cancelled && ! Invoice::query()->where('replaces_invoice_id', $this->getRecord()->id)->exists())
                ->url(fn (): string => InvoiceResource::getUrl('create', ['replacement' => $this->getRecord()->id])),
            Action::make('cancelInvoice')->label(fn (): string => __('invoices.actions.cancel'))
                ->color('danger')->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('cancel', $this->getRecord()))
                ->action(function (): void {
                    $landlord = auth()->user();
                    $invoice = $this->getRecord();
                    abort_unless($landlord instanceof User && $invoice instanceof Invoice, 403);
                    app(CancelInvoiceAction::class)->handle($landlord, $invoice);
                    $this->refreshFormData(['status']);
                }),
        ];
    }
}
