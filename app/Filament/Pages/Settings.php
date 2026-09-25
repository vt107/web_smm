<?php

namespace App\Filament\Pages;

use App\Filament\NavGroup;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Chỉnh các giá trị trong bảng settings (mặc định ở config/smm.php → settings). */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::SYSTEM;

    protected static ?string $title = 'Cài đặt';

    protected static ?int $navigationSort = 99;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(collect(array_keys(config('smm.settings')))
            ->mapWithKeys(fn (string $key) => [$key => Setting::get($key)])
            ->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Đơn hàng')->columns(2)->schema([
                TextInput::make('order_max_send_attempts')->label('Số lần gửi provider tối đa')->integer()->minValue(1)->maxValue(20)->required()
                    ->helperText('Hết số lần này mà provider vẫn lỗi → huỷ đơn và tự hoàn tiền.'),
                TextInput::make('price_sync_markup_percent')->label('% lãi tự áp khi đồng bộ giá')->numeric()->minValue(0)
                    ->helperText('Bỏ trống = không tự đổi giá bán khi giá vốn thay đổi.'),
                TextInput::make('low_provider_balance_alert')->label('Cảnh báo khi số dư provider dưới (USD)')->numeric()->minValue(0)->required(),
            ]),
            Section::make('Nạp tiền')->columns(2)->schema([
                TextInput::make('deposit_claim_window_hours')->label('Cho khách nhận giao dịch trong (giờ)')->integer()->minValue(1)->required()
                    ->helperText('Phiếu đã hết hạn vẫn khớp được giao dịch đến trễ trong khoảng thời gian này.'),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([Action::make('save')->label('Lưu cài đặt')->submit('save')]),
                ]),
        ]);
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::set($key, $value === '' ? null : $value);
        }

        Notification::make()->success()->title('Đã lưu cài đặt')->send();
    }
}
