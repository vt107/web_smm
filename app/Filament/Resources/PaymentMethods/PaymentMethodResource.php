<?php

namespace App\Filament\Resources\PaymentMethods;

use App\Enums\ActiveStatus;
use App\Enums\PaymentGateway;
use App\Filament\Concerns\StaffPermissions;
use App\Filament\NavGroup;
use App\Filament\Resources\PaymentMethods\Pages\CreatePaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\EditPaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
use App\Filament\Support\Fmt;
use App\Models\PaymentMethod;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PaymentMethodResource extends Resource
{
    use StaffPermissions;

    protected static ?string $model = PaymentMethod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::FINANCE;

    protected static ?string $modelLabel = 'payment method';

    protected static ?string $pluralModelLabel = 'Payment methods';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('General')->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->label('Display name')->required()->maxLength(100),
                Select::make('gateway')->label('Gateway')->options(PaymentGateway::class)->required()->live(),
                TextInput::make('currency')->label('Payment currency')->required()->maxLength(10)->placeholder('VND / USDT'),
                TextInput::make('exchange_rate')->label('Exchange rate (per 1 USD)')->numeric()->minValue(0.000001)->required()->default(1),
                TextInput::make('min_amount')->label('Min. deposit (USD)')->numeric()->minValue(0)->required()->default(1),
                TextInput::make('max_amount')->label('Max. deposit (USD)')->numeric()->minValue(0)->required()->default(10000),
                TextInput::make('bonus_percent')->label('Bonus (%)')->numeric()->minValue(0)->maxValue(100)->default(0),
                TextInput::make('deposit_ttl_minutes')->label('Deposit expires after (minutes)')->integer()->minValue(5)->default(60),
                Select::make('status')->label('Status')->options(ActiveStatus::class)->required()->default(ActiveStatus::Active),
                TextInput::make('sort')->label('Sort order')->numeric()->default(0),
                Textarea::make('instructions')->label('Instructions for customers')->rows(3)->columnSpanFull(),
            ]),
            Section::make('SePay settings')->columns(2)->columnSpanFull()
                ->visible(fn (Get $get) => self::gateway($get) === PaymentGateway::Sepay)
                ->schema([
                    TextInput::make('config.bank_name')->label('Bank')->placeholder('MBBank')->required(),
                    TextInput::make('config.account_number')->label('Account number')->required(),
                    TextInput::make('config.account_name')->label('Account holder'),
                    TextInput::make('config.webhook_key')->label('Webhook API key')->password()->revealable()->required()
                        ->helperText('Enter this key in the SePay webhook settings (API Key authentication).'),
                    TextEntry::make('webhook_url')->label('Webhook URL')->columnSpanFull()->copyable()
                        ->state(fn (?PaymentMethod $record) => $record ? url("/api/webhooks/sepay/{$record->id}") : 'Save the method to get the URL'),
                ]),
            Section::make('Crypto wallet settings')->columns(2)->columnSpanFull()
                ->visible(fn (Get $get) => self::gateway($get) === PaymentGateway::Crypto)
                ->schema([
                    Select::make('config.network')->label('Network')->required()->live()
                        ->options(['tron' => 'TRON (TRC20) – TronGrid', 'bsc' => 'BNB Smart Chain (BEP20) – Etherscan V2']),
                    TextInput::make('config.address')->label('Receiving wallet address')->required(),
                    TextInput::make('config.token_contract')->label('Contract token')->required()
                        ->helperText('USDT TRC20: TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t — USDT BEP20: 0x55d398326f99059fF775485246999027B3197955'),
                    TextInput::make('config.token_decimals')->label('Token decimals')->integer()->required()->default(6),
                    TextInput::make('config.api_key')->label('API key explorer')->password()->revealable()
                        ->helperText('TronGrid API key (TRON) or Etherscan API key (BSC).'),
                    TextInput::make('config.chain_id')->label('Chain ID')->integer()->default(56)
                        ->visible(fn (Get $get) => $get('config.network') === 'bsc'),
                    TextInput::make('config.min_confirmations')->label('Min. confirmations')->integer()->default(15)
                        ->visible(fn (Get $get) => $get('config.network') === 'bsc'),
                    TextInput::make('config.start_block')->label('Scan from block')->integer()
                        ->visible(fn (Get $get) => $get('config.network') === 'bsc'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('Name')->weight('bold'),
                TextColumn::make('gateway')->label('Gateway')->badge()->color('gray'),
                TextColumn::make('exchange_rate')->label('Exchange rate')
                    ->formatStateUsing(fn (PaymentMethod $r) => rtrim(rtrim($r->exchange_rate, '0'), '.').' '.$r->currency.' = $1'),
                TextColumn::make('min_amount')->label('Min – Max')
                    ->formatStateUsing(fn (PaymentMethod $r) => Fmt::usd($r->min_amount).' – '.Fmt::usd($r->max_amount)),
                TextColumn::make('bonus_percent')->label('Bonus')->suffix('%'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('last_scanned_at')->label('Last wallet scan')->since()->placeholder('—'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentMethods::route('/'),
            'create' => CreatePaymentMethod::route('/create'),
            'edit' => EditPaymentMethod::route('/{record}/edit'),
        ];
    }

    private static function gateway(Get $get): ?PaymentGateway
    {
        $value = $get('gateway');

        return $value instanceof PaymentGateway ? $value : PaymentGateway::tryFrom((string) $value);
    }
}
