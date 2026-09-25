<?php

namespace App\Filament\Resources\Users;

use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\InsufficientBalanceException;
use App\Filament\Access;
use App\Filament\Concerns\StaffPermissions;
use App\Filament\NavGroup;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Users\RelationManagers\TransactionsRelationManager;
use App\Filament\Support\Fmt;
use App\Models\Currency;
use App\Models\User;
use App\Services\BalanceService;
use App\Services\TwoFactorService;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    use StaffPermissions;

    /** Nhân viên Support được xem (không sửa). */
    protected static function supportAbilities(): array
    {
        return ['viewAny'];
    }

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::CUSTOMERS;

    protected static ?string $modelLabel = 'customer';

    protected static ?string $pluralModelLabel = 'Customers';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'username';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Account')->columns(2)->schema([
                TextInput::make('name')->label('Full name')->required()->maxLength(100),
                TextInput::make('username')->label('Username')->required()->maxLength(50)->alphaDash()->unique(ignoreRecord: true),
                TextInput::make('email')->label('Email')->email()->required()->maxLength(191)->unique(ignoreRecord: true),
                TextInput::make('password')->label('Password')->password()->revealable()->minLength(8)
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave empty to keep the current password.' : null),
            ]),
            Section::make('Role & level')->columns(2)->schema([
                Select::make('level_id')->label('Levels')->relationship('level', 'name')->required(),
                Select::make('status')->label('Status')->options(UserStatus::class)->required()->default(UserStatus::Active),
                Select::make('role')->label('Role')->options(UserRole::class)->required()->default(UserRole::User),
                Select::make('currency')->label('Display currencies')->options(fn () => Currency::query()->pluck('name', 'code'))
                    ->required()->default(Currency::BASE),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('username')->label('Customers')->searchable(['username', 'email', 'name'])
                    ->description(fn (User $r) => $r->email),
                TextColumn::make('level.name')->label('Level')->badge()->color('gray'),
                TextColumn::make('balance')->label('Balance')->formatStateUsing(fn ($state) => Fmt::usd($state))->sortable(),
                TextColumn::make('total_spent')->label('Spent')->formatStateUsing(fn ($state) => Fmt::usd($state))->sortable(),
                TextColumn::make('role')->label('Role')->badge()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->label('Status')->badge(),
                IconColumn::make('two_factor')->label('2FA')->boolean()
                    ->state(fn (User $record) => $record->hasTwoFactorEnabled())
                    ->toggleable(),
                TextColumn::make('created_at')->label('Registered')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('level_id')->label('Levels')->relationship('level', 'name'),
                SelectFilter::make('status')->label('Status')->options(UserStatus::class),
                SelectFilter::make('role')->label('Role')->options(UserRole::class),
            ])
            ->recordActions([
                EditAction::make(),
                self::adjustBalanceAction(),
                self::resetTwoFactorAction(),
            ]);
    }

    /** Tắt toàn bộ 2FA cho khách (mất điện thoại + mất mã khôi phục). */
    public static function resetTwoFactorAction(): Action
    {
        return Action::make('resetTwoFactor')
            ->label('Reset 2FA')
            ->icon(Heroicon::OutlinedShieldExclamation)
            ->color('danger')
            ->visible(fn (User $record) => Access::isAdmin() && $record->hasTwoFactorEnabled())
            ->requiresConfirmation()
            ->modalDescription('Turns off the authenticator app and email codes for this customer. Only do this after verifying their identity.')
            ->action(function (User $record) {
                app(TwoFactorService::class)->reset($record);
                Notification::make()->success()->title('Two-factor authentication has been reset')->send();
            });
    }

    public static function adjustBalanceAction(): Action
    {
        return Action::make('adjustBalance')
            ->label('Adjust balance')
            ->visible(fn () => Access::isAdmin())
            ->icon(Heroicon::OutlinedBanknotes)
            ->schema([
                Select::make('type')->label('Type')->required()->default(TransactionType::AdminAdd->value)
                    ->options([
                        TransactionType::AdminAdd->value => 'Add funds',
                        TransactionType::AdminDeduct->value => 'Deduct funds',
                    ]),
                TextInput::make('amount')->label('Amount (USD)')->numeric()->minValue(0.000001)->required()->prefix('$'),
                TextInput::make('description')->label('Note (visible to customer)')->maxLength(255),
            ])
            ->action(function (User $record, array $data) {
                $type = TransactionType::from($data['type']);
                $amount = Money::round($data['amount']);

                try {
                    app(BalanceService::class)->change(
                        $record,
                        $type === TransactionType::AdminDeduct ? Money::sub(0, $amount) : $amount,
                        $type,
                        description: $data['description'] ?: null,
                        createdBy: auth()->user(),
                    );
                } catch (InsufficientBalanceException) {
                    Notification::make()->danger()->title('Insufficient balance to deduct')->send();

                    return;
                }

                Notification::make()->success()->title('Balance updated: '.Fmt::usd($record->fresh()->balance))->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
