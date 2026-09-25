<?php

namespace App\Filament\Pages;

use App\Filament\NavGroup;
use App\Http\Controllers\Public\LandingController;
use App\Models\Setting;
use App\Support\Site;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * Cấu hình website cho người không rành kỹ thuật: tên, logo, SEO, nội dung landing, liên hệ.
 * Lưu vào bảng settings với key "site.*" (đọc qua App\Support\Site).
 */
class WebsiteSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = NavGroup::SYSTEM;

    protected static ?string $title = 'Website settings';

    protected static ?int $navigationSort = 98;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(collect(array_keys(config('smm.site')))
            ->mapWithKeys(fn (string $key) => [$key => Site::get($key)])
            ->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Tabs::make()->persistTabInQueryString()->tabs([
                Tab::make('General')->icon(Heroicon::OutlinedHome)->schema([
                    Section::make('Brand')->columns(2)->schema([
                        TextInput::make('name')->label('Website name')->required()->maxLength(60)
                            ->helperText('Shown in the header, browser tab, emails and the admin panel.'),
                        TextInput::make('tagline')->label('Tagline')->maxLength(120)
                            ->helperText('A short slogan, e.g. "Cheapest & fastest SMM panel".'),
                        FileUpload::make('logo')->label('Logo')->image()->disk('public')->directory('site')
                            ->maxSize(1024)->imagePreviewHeight('80')
                            ->helperText('Square image works best (PNG or SVG, max 1 MB). Leave empty to use the default logo.'),
                        FileUpload::make('favicon')->label('Browser tab icon (favicon)')->image()->disk('public')->directory('site')
                            ->maxSize(512)->imagePreviewHeight('48')
                            ->helperText('Small square image (e.g. 64×64 PNG). Leave empty to use the logo.'),
                        Toggle::make('show_name')->label('Show website name next to the logo')
                            ->helperText('Turn off if your logo already contains the name.'),
                    ]),
                ]),
                Tab::make('SEO')->icon(Heroicon::OutlinedMagnifyingGlass)->schema([
                    Section::make('Search engines & social sharing')->columns(2)->schema([
                        TextInput::make('seo_title')->label('Homepage title (Google)')->maxLength(70)->columnSpanFull()
                            ->placeholder(fn () => Site::name().' – '.Site::get('tagline'))
                            ->helperText('Best 50–60 characters. Leave empty to use "Website name – Tagline".'),
                        Textarea::make('description')->label('Description (Google)')->rows(3)->maxLength(170)->required()->columnSpanFull()
                            ->helperText('The text shown under your site in Google results. Best 140–160 characters.'),
                        FileUpload::make('og_image')->label('Sharing image')->image()->disk('public')->directory('site')
                            ->maxSize(2048)->imagePreviewHeight('100')
                            ->helperText('Shown when a link is shared on Facebook, Telegram, Zalo… Recommended 1200×630 px.'),
                        Section::make()->schema([
                            Toggle::make('allow_indexing')->label('Allow search engines to index the website')
                                ->helperText('Turn off while the site is still being set up.'),
                            TextInput::make('google_analytics_id')->label('Google Analytics ID')->placeholder('G-XXXXXXXXXX')
                                ->regex('/^G-[A-Z0-9]+$/')->helperText('Optional. Paste the Measurement ID from Google Analytics.'),
                        ]),
                    ]),
                ]),
                Tab::make('Landing page')->icon(Heroicon::OutlinedSparkles)->schema([
                    Section::make('Hero')->schema([
                        TextInput::make('hero_title')->label('Main headline')->required()->maxLength(90),
                        Textarea::make('hero_subtitle')->label('Sub headline')->rows(3)->required()->maxLength(300),
                    ]),
                    Section::make('Frequently asked questions')->schema([
                        Repeater::make('faq')->hiddenLabel()->reorderable()->collapsible()->defaultItems(0)
                            ->itemLabel(fn (array $state) => $state['question'] ?? null)
                            ->addActionLabel('Add question')
                            ->schema([
                                TextInput::make('question')->label('Question')->required()->maxLength(150),
                                Textarea::make('answer')->label('Answer')->rows(3)->required()->maxLength(1000),
                            ]),
                    ]),
                ]),
                Tab::make('Contact & footer')->icon(Heroicon::OutlinedChatBubbleLeftRight)->schema([
                    Section::make()->columns(2)->schema([
                        TextInput::make('support_email')->label('Support email')->email(),
                        TextInput::make('telegram_url')->label('Telegram link')->url()->placeholder('https://t.me/yourname'),
                        TextInput::make('whatsapp_url')->label('WhatsApp link')->url()->placeholder('https://wa.me/84900000000'),
                        TextInput::make('footer_text')->label('Footer text')->maxLength(200)
                            ->placeholder(fn () => '© '.now()->year.' '.Site::name().'. All rights reserved.'),
                    ]),
                ]),
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
                    Actions::make([Action::make('save')->label('Save changes')->submit('save')]),
                ]),
        ]);
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::set("site.{$key}", $value === '' ? null : $value);
        }

        // Landing page cache số liệu → xoá để thay đổi hiện ngay.
        Cache::forget(LandingController::CACHE_KEY);

        Notification::make()->success()->title('Website settings saved')->send();
    }
}
