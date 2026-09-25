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

    protected static ?string $title = 'Settings';

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
            Section::make('Orders')->columns(2)->schema([
                TextInput::make('order_max_send_attempts')->label('Max. provider send attempts')->integer()->minValue(1)->maxValue(20)->required()
                    ->helperText('After this many failed attempts the order is canceled and refunded automatically.'),
                TextInput::make('price_sync_markup_percent')->label('Auto markup % on price sync')->numeric()->minValue(0)
                    ->helperText('Leave empty to keep selling prices unchanged when costs change.'),
                TextInput::make('low_provider_balance_alert')->label('Low provider balance alert (USD)')->numeric()->minValue(0)->required(),
            ]),
            Section::make('Deposit')->columns(2)->schema([
                TextInput::make('deposit_claim_window_hours')->label('Claim window for late transactions (hours)')->integer()->minValue(1)->required()
                    ->helperText('Expired deposits can still be matched with late transactions within this window.'),
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
                    Actions::make([Action::make('save')->label('Save settings')->submit('save')]),
                ]),
        ]);
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::set($key, $value === '' ? null : $value);
        }

        Notification::make()->success()->title('Settings saved')->send();
    }
}
