<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Enums\TicketStatus;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label('Reply')
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->schema([Textarea::make('message')->label('Description')->rows(6)->required()->maxLength(5000)])
                ->action(function (Ticket $record, array $data) {
                    app(TicketService::class)->reply($record, auth()->user(), $data['message'], true);
                    Notification::make()->success()->title('Reply sent')->send();
                    $this->refreshFormData(['status']);
                }),
            Action::make('close')
                ->label('Close ticket')
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (Ticket $record) => $record->status !== TicketStatus::Closed)
                ->action(fn (Ticket $record) => app(TicketService::class)->close($record)),
        ];
    }
}
