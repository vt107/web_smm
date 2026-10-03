<?php

namespace App\Filament\Pages;

use App\Enums\TelegramEvent;
use App\Filament\Access;
use App\Filament\NavGroup;
use App\Http\Controllers\Public\LandingController;
use App\Models\Setting;
use App\Services\TelegramNotifier;
use App\Support\Demo\DemoMode;
use App\Support\MailSettings;
use App\Support\Site;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Throwable;
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

    /** Không nhận SVG: file SVG có thể chứa script chạy trên domain của website. */
    private const IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon'];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Access::isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill(collect(array_keys(config('smm.site')))
            ->mapWithKeys(fn (string $key) => [$key => Site::get($key)])
            // Không đổ mật khẩu SMTP (đã mã hoá) ra form; chỉ lưu khi nhập mới.
            ->put('mail_password', null)
            ->put('telegram_bot_token', null)
            ->all());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testTelegram')
                ->label('Send test Telegram message')
                ->icon(Heroicon::OutlinedChatBubbleOvalLeftEllipsis)
                ->color('gray')
                ->action(function (TelegramNotifier $telegram) {
                    DemoMode::abortIfEnabled(); // gửi tin Telegram thật

                    if (! $telegram->isConfigured()) {
                        Notification::make()->warning()->title('Please fill in and save the Telegram bot token and chat IDs first.')->send();

                        return;
                    }

                    try {
                        $telegram->sendNow('✅ <b>'.e(Site::name()).'</b>'."\n".'Test message – Telegram notifications are working.');
                        Notification::make()->success()->title('Test message sent to '.count($telegram->chatIds()).' chat(s)')->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title('Could not send Telegram message')->body($e->getMessage())->persistent()->send();
                    }
                }),
            Action::make('testEmail')
                ->label('Send test email')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->schema([
                    TextInput::make('to')->label('Send to')->email()->required()->default(fn () => auth()->user()->email),
                ])
                ->action(function (array $data) {
                    DemoMode::abortIfEnabled(); // gửi email thật

                    if (! MailSettings::isConfigured()) {
                        Notification::make()->warning()->title('Please fill in and save the Email (SMTP) settings first.')->send();

                        return;
                    }

                    try {
                        MailSettings::apply();
                        Mail::raw('This is a test email from '.Site::name().'. Your email settings are working.', function ($message) use ($data) {
                            $message->to($data['to'])->subject('Test email from '.Site::name());
                        });
                        Notification::make()->success()->title('Test email sent to '.$data['to'])->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title('Could not send email')->body($e->getMessage())->persistent()->send();
                    }
                }),
        ];
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
                        FileUpload::make('logo')->label('Logo')->image()->acceptedFileTypes(self::IMAGE_TYPES)->disk('public')->directory('site')
                            ->maxSize(1024)->imagePreviewHeight('80')
                            ->helperText('Square image works best (PNG or SVG, max 1 MB). Leave empty to use the default logo.'),
                        FileUpload::make('favicon')->label('Browser tab icon (favicon)')->image()->acceptedFileTypes(self::IMAGE_TYPES)->disk('public')->directory('site')
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
                        FileUpload::make('og_image')->label('Sharing image')->image()->acceptedFileTypes(self::IMAGE_TYPES)->disk('public')->directory('site')
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
                Tab::make('Email')->icon(Heroicon::OutlinedEnvelope)->schema([
                    Section::make('Outgoing email (SMTP)')
                        ->description('Used for password reset emails. Get these details from your email provider (Gmail, Zoho, Mailgun, SendGrid…). Save, then click "Send test email" at the top.')
                        ->columns(2)
                        ->schema([
                            TextInput::make('mail_host')->label('SMTP server')->placeholder('smtp.gmail.com')
                                ->helperText('Leave empty to disable sending emails.'),
                            TextInput::make('mail_port')->label('Port')->integer()->placeholder('587'),
                            Select::make('mail_security')->label('Security')->options([
                                'tls' => 'TLS / STARTTLS (usually port 587)',
                                'ssl' => 'SSL (usually port 465)',
                            ])->default('tls'),
                            TextInput::make('mail_username')->label('Username')->placeholder('you@example.com'),
                            TextInput::make('mail_password')->label('Password')->password()->revealable()
                                ->helperText(fn () => Site::get('mail_password') ? 'A password is saved. Leave empty to keep it.' : 'For Gmail, use an "App password".'),
                            TextInput::make('mail_from_address')->label('Sender email')->email()
                                ->helperText('Leave empty to use the username.'),
                            TextInput::make('mail_from_name')->label('Sender name')
                                ->placeholder(fn () => Site::name()),
                        ]),
                ]),
                Tab::make('Telegram')->icon(Heroicon::OutlinedBellAlert)->schema([
                    Section::make('Telegram notifications for admins')
                        ->description(new HtmlString(
                            '1. In Telegram, open <b>@BotFather</b>, send <code>/newbot</code> and copy the bot token.<br>'
                            .'2. Paste the token below and click <b>Save changes</b>.<br>'
                            .'3. Send any message to your bot (or add the bot to a group and send a message there).<br>'
                            .'4. Click <b>Find chat IDs</b>, add the IDs, save, then click <b>Send test Telegram message</b> at the top.'
                        ))
                        ->columns(2)
                        ->schema([
                            TextInput::make('telegram_bot_token')->label('Bot token')->password()->revealable()
                                ->placeholder('123456789:AA...')
                                ->helperText(fn () => Site::get('telegram_bot_token') ? 'A token is saved. Leave empty to keep it.' : null),
                            Textarea::make('telegram_chat_ids')->label('Chat IDs')->rows(3)
                                ->placeholder("123456789\n-1001234567890")
                                ->helperText('One per line. Group IDs start with -100.')
                                ->hintAction(
                                    Action::make('findChats')
                                        ->label('Find chat IDs')
                                        ->icon(Heroicon::OutlinedMagnifyingGlass)
                                        ->action(function (TelegramNotifier $telegram, Get $get) {
                                            DemoMode::abortIfEnabled(); // gọi API Telegram

                                            try {
                                                $token = filled($get('telegram_bot_token')) ? trim($get('telegram_bot_token')) : null;
                                                $chats = $telegram->discoverChats($token);
                                            } catch (Throwable $e) {
                                                Notification::make()->danger()->title('Could not reach Telegram')->body($e->getMessage())->send();

                                                return;
                                            }

                                            if ($chats === []) {
                                                Notification::make()->warning()->title('No chats found yet')
                                                    ->body('Send a message to your bot first, then try again.')->send();

                                                return;
                                            }

                                            Notification::make()->success()->title('Chats that messaged your bot')->persistent()
                                                ->body(collect($chats)->map(fn (array $c) => "{$c['id']} – {$c['name']} ({$c['type']})")->implode("\n"))
                                                ->send();
                                        }),
                                ),
                            CheckboxList::make('telegram_events')->label('Send a message when…')->columnSpanFull()
                                ->options(TelegramEvent::class),
                        ]),
                ]),
                Tab::make('Legal pages')->icon(Heroicon::OutlinedScale)->schema([
                    Section::make()
                        ->description('Shown at /terms and /privacy and linked in the footer. {site_name} and {support_email} are replaced automatically.')
                        ->schema([
                            MarkdownEditor::make('terms_content')->label('Terms of Service')->required()
                                ->toolbarButtons([['heading', 'bold', 'italic', 'link'], ['bulletList', 'orderedList'], ['undo', 'redo']]),
                            MarkdownEditor::make('privacy_content')->label('Privacy Policy')->required()
                                ->toolbarButtons([['heading', 'bold', 'italic', 'link'], ['bulletList', 'orderedList'], ['undo', 'redo']]),
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
            if (in_array($key, ['mail_password', 'telegram_bot_token'], true)) {
                if (blank($value)) {
                    continue; // giữ giá trị đã lưu
                }

                $value = $key === 'mail_password' ? MailSettings::encryptPassword($value) : TelegramNotifier::encryptToken($value);
            }

            Setting::set("site.{$key}", $value === '' ? null : $value);
        }

        // Landing page cache số liệu → xoá để thay đổi hiện ngay.
        Cache::forget(LandingController::CACHE_KEY);

        Notification::make()->success()->title('Website settings saved')->send();
    }
}
