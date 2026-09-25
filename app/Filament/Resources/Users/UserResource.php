<?php

namespace App\Filament\Resources\Users;

use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\InsufficientBalanceException;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::CUSTOMERS;

    protected static ?string $modelLabel = 'khách hàng';

    protected static ?string $pluralModelLabel = 'Khách hàng';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'username';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Tài khoản')->columns(2)->schema([
                TextInput::make('name')->label('Họ tên')->required()->maxLength(100),
                TextInput::make('username')->label('Username')->required()->maxLength(50)->alphaDash()->unique(ignoreRecord: true),
                TextInput::make('email')->label('Email')->email()->required()->maxLength(191)->unique(ignoreRecord: true),
                TextInput::make('password')->label('Mật khẩu')->password()->revealable()->minLength(8)
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create')
                    ->helperText(fn (string $operation) => $operation === 'edit' ? 'Bỏ trống nếu không đổi.' : null),
            ]),
            Section::make('Phân quyền & cấp bậc')->columns(2)->schema([
                Select::make('level_id')->label('Cấp bậc')->relationship('level', 'name')->required(),
                Select::make('status')->label('Trạng thái')->options(UserStatus::class)->required()->default(UserStatus::Active),
                Select::make('role')->label('Vai trò')->options(UserRole::class)->required()->default(UserRole::User),
                Select::make('currency')->label('Tiền tệ hiển thị')->options(fn () => Currency::query()->pluck('name', 'code'))
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
                TextColumn::make('username')->label('Khách hàng')->searchable(['username', 'email', 'name'])
                    ->description(fn (User $r) => $r->email),
                TextColumn::make('level.name')->label('Cấp')->badge()->color('gray'),
                TextColumn::make('balance')->label('Số dư')->formatStateUsing(fn ($state) => Fmt::usd($state))->sortable(),
                TextColumn::make('total_spent')->label('Đã chi')->formatStateUsing(fn ($state) => Fmt::usd($state))->sortable(),
                TextColumn::make('role')->label('Vai trò')->badge()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->label('Trạng thái')->badge(),
                TextColumn::make('created_at')->label('Đăng ký')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('level_id')->label('Cấp bậc')->relationship('level', 'name'),
                SelectFilter::make('status')->label('Trạng thái')->options(UserStatus::class),
                SelectFilter::make('role')->label('Vai trò')->options(UserRole::class),
            ])
            ->recordActions([
                EditAction::make(),
                self::adjustBalanceAction(),
            ]);
    }

    public static function adjustBalanceAction(): Action
    {
        return Action::make('adjustBalance')
            ->label('Cộng/trừ tiền')
            ->icon(Heroicon::OutlinedBanknotes)
            ->schema([
                Select::make('type')->label('Loại')->required()->default(TransactionType::AdminAdd->value)
                    ->options([
                        TransactionType::AdminAdd->value => 'Cộng tiền',
                        TransactionType::AdminDeduct->value => 'Trừ tiền',
                    ]),
                TextInput::make('amount')->label('Số tiền (USD)')->numeric()->minValue(0.000001)->required()->prefix('$'),
                TextInput::make('description')->label('Ghi chú (khách nhìn thấy)')->maxLength(255),
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
                    Notification::make()->danger()->title('Số dư không đủ để trừ')->send();

                    return;
                }

                Notification::make()->success()->title('Đã cập nhật số dư: '.Fmt::usd($record->fresh()->balance))->send();
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
