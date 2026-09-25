<?php

namespace App\Filament\Resources\Tickets;

use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Filament\NavGroup;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Models\Ticket;
use App\Models\TicketMessage;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::SUPPORT;

    protected static ?string $modelLabel = 'ticket';

    protected static ?string $pluralModelLabel = 'Support tickets';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_reply_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('subject')->label('Subject')->searchable()->limit(60)
                    ->description(fn (Ticket $r) => $r->category->getLabel().($r->order_ids ? " · Orders: {$r->order_ids}" : '')),
                TextColumn::make('user.username')->label('Customer')->searchable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('last_reply_at')->label('Updated')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(TicketStatus::class)->multiple()
                    ->default([TicketStatus::Open->value, TicketStatus::CustomerReply->value]),
                SelectFilter::make('category')->label('Type')->options(TicketCategory::class),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Conversation')->columnSpan(2)->schema([
                RepeatableEntry::make('messages')->hiddenLabel()->contained(false)->schema([
                    TextEntry::make('user.username')->hiddenLabel()->weight('bold')
                        ->badge()->color(fn (TicketMessage $m) => $m->is_staff ? 'success' : 'gray')
                        ->formatStateUsing(fn (TicketMessage $m) => $m->is_staff ? "Support · {$m->user->username}" : $m->user->username)
                        ->helperText(fn (TicketMessage $m) => $m->created_at->format('d/m/Y H:i')),
                    TextEntry::make('message')->hiddenLabel()->prose()->extraAttributes(['style' => 'white-space: pre-line']),
                    ImageEntry::make('attachments')->hiddenLabel()->disk('public')->imageHeight(120)
                        ->visible(fn (TicketMessage $m) => ! empty($m->attachments)),
                ]),
            ]),
            Section::make('Details')->columnSpan(1)->schema([
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('category')->label('Type')->badge()->color('gray'),
                TextEntry::make('user.username')->label('Customer')->helperText(fn (Ticket $r) => $r->user->email),
                TextEntry::make('order_ids')->label('Related orders')->placeholder('—')->copyable(),
                TextEntry::make('created_at')->label('Created')->dateTime('d/m/Y H:i'),
            ]),
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return Ticket::query()->whereIn('status', [TicketStatus::Open, TicketStatus::CustomerReply])->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'view' => ViewTicket::route('/{record}'),
        ];
    }
}
