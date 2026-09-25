<?php

namespace App\Services;

use App\Enums\TelegramEvent;
use App\Jobs\SendTelegramMessage;
use App\Support\Site;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Gửi thông báo quan trọng về Telegram cho admin. Cấu hình ở Admin → Website settings → Telegram.
 * Nội dung dạng HTML của Telegram – dữ liệu do khách nhập phải qua e().
 */
class TelegramNotifier
{
    private const API = 'https://api.telegram.org/bot';

    public function isConfigured(): bool
    {
        return filled($this->token()) && $this->chatIds() !== [];
    }

    public function isEnabled(TelegramEvent $event): bool
    {
        return $this->isConfigured() && in_array($event->value, (array) Site::get('telegram_events'), true);
    }

    /**
     * Đưa thông báo vào queue (gửi sau khi transaction DB commit).
     * $dedupeKey: cùng key chỉ gửi 1 lần trong $dedupeMinutes (vd cảnh báo số dư provider mỗi giờ).
     */
    public function notify(TelegramEvent $event, string $html, ?string $dedupeKey = null, int $dedupeMinutes = 360): void
    {
        try {
            if (! $this->isEnabled($event)) {
                return;
            }

            if ($dedupeKey !== null && ! Cache::add("telegram:{$dedupeKey}", true, now()->addMinutes($dedupeMinutes))) {
                return;
            }

            SendTelegramMessage::dispatch('<b>'.e(Site::name()).'</b>'."\n".$html)->afterCommit();
        } catch (Throwable $e) {
            // Thông báo lỗi không được làm hỏng nghiệp vụ chính (nạp tiền, đặt đơn…).
            report($e);
        }
    }

    /**
     * Gửi ngay tới mọi chat ID.
     *
     * @throws RuntimeException khi Telegram báo lỗi
     */
    public function sendNow(string $html): void
    {
        $token = $this->token() ?? throw new RuntimeException('Telegram bot token is not set');

        foreach ($this->chatIds() as $chatId) {
            $response = Http::timeout(15)->asJson()->post(self::API.$token.'/sendMessage', [
                'chat_id' => $chatId,
                'text' => $html,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if (! $response->json('ok')) {
                throw new RuntimeException("Chat {$chatId}: ".($response->json('description') ?? 'HTTP '.$response->status()));
            }
        }
    }

    /**
     * Các chat đã nhắn tin cho bot gần đây (giúp admin tìm chat ID).
     *
     * @return list<array{id: string, name: string, type: string}>
     */
    public function discoverChats(?string $token = null): array
    {
        $token ??= $this->token() ?? throw new RuntimeException('Telegram bot token is not set');
        $response = Http::timeout(15)->get(self::API.$token.'/getUpdates');

        if (! $response->json('ok')) {
            throw new RuntimeException($response->json('description') ?? 'HTTP '.$response->status());
        }

        return collect($response->json('result', []))
            ->map(fn (array $update) => $update['message']['chat'] ?? $update['my_chat_member']['chat'] ?? $update['channel_post']['chat'] ?? null)
            ->filter()
            ->unique('id')
            ->map(fn (array $chat) => [
                'id' => (string) $chat['id'],
                'name' => $chat['title'] ?? trim(($chat['first_name'] ?? '').' '.($chat['last_name'] ?? '')) ?: ($chat['username'] ?? ''),
                'type' => $chat['type'],
            ])
            ->values()
            ->all();
    }

    public static function encryptToken(string $token): string
    {
        return Crypt::encryptString(trim($token));
    }

    /** @return list<string> */
    public function chatIds(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) Site::get('telegram_chat_ids')))));
    }

    private function token(): ?string
    {
        $encrypted = Site::get('telegram_bot_token');

        try {
            return $encrypted ? Crypt::decryptString($encrypted) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
