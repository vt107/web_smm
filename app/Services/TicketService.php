<?php

namespace App\Services;

use App\Enums\TelegramEvent;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Exceptions\SmmException;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public const MAX_OPEN_TICKETS = 10;

    public function __construct(private readonly TelegramNotifier $telegram) {}

    /**
     * @param  list<UploadedFile>  $attachments
     *
     * @throws SmmException
     */
    public function open(User $user, string $subject, TicketCategory $category, ?string $orderIds, string $message, array $attachments = []): Ticket
    {
        if ($user->tickets()->where('status', '!=', TicketStatus::Closed)->count() >= self::MAX_OPEN_TICKETS) {
            throw new SmmException('You have too many open tickets, please wait for a reply');
        }

        $orderIds = $this->normalizeOrderIds($user, $orderIds, $category->requiresOrders());

        return DB::transaction(function () use ($user, $subject, $category, $orderIds, $message, $attachments) {
            $ticket = new Ticket(['subject' => $subject, 'category' => $category, 'order_ids' => $orderIds]);
            $ticket->user()->associate($user);
            $ticket->status = TicketStatus::Open;
            $ticket->last_reply_at = now();
            $ticket->save();

            $this->addMessage($ticket, $user, $message, false, $attachments);
            $this->notifyStaff($ticket, $user, $message, 'New ticket');

            return $ticket;
        });
    }

    /**
     * Khách hoặc nhân viên trả lời. Khách trả lời ticket đã đóng sẽ mở lại ticket.
     *
     * @param  list<UploadedFile>  $attachments
     */
    public function reply(Ticket $ticket, User $author, string $message, bool $isStaff, array $attachments = []): TicketMessage
    {
        return DB::transaction(function () use ($ticket, $author, $message, $isStaff, $attachments) {
            $reply = $this->addMessage($ticket, $author, $message, $isStaff, $attachments);

            $ticket->status = $isStaff ? TicketStatus::Answered : TicketStatus::CustomerReply;
            $ticket->last_reply_at = now();
            $ticket->save();

            if (! $isStaff) {
                $this->notifyStaff($ticket, $author, $message, 'Customer replied');
            }

            return $reply;
        });
    }

    public function close(Ticket $ticket): void
    {
        $ticket->status = TicketStatus::Closed;
        $ticket->save();
    }

    private function notifyStaff(Ticket $ticket, User $author, string $message, string $heading): void
    {
        $this->telegram->notify(TelegramEvent::Ticket, implode("\n", array_filter([
            "🎫 <b>{$heading}</b> #{$ticket->id} · ".e($ticket->category->getLabel()),
            'Customer: '.e($author->username),
            'Subject: '.e($ticket->subject),
            $ticket->order_ids ? 'Orders: '.e($ticket->order_ids) : null,
            e(mb_strimwidth($message, 0, 300, '…')),
            url("/admin/tickets/{$ticket->id}"),
        ])));
    }

    /** @param  list<UploadedFile>  $attachments */
    private function addMessage(Ticket $ticket, User $author, string $message, bool $isStaff, array $attachments): TicketMessage
    {
        $paths = array_map(fn (UploadedFile $file) => $file->store("tickets/{$ticket->id}", 'public'), $attachments);

        return $ticket->messages()->create([
            'user_id' => $author->id,
            'is_staff' => $isStaff,
            'message' => $message,
            'attachments' => $paths ?: null,
        ]);
    }

    /** Chuẩn hoá "1, 2 3" → "1,2,3" và kiểm tra đơn thuộc về user. */
    private function normalizeOrderIds(User $user, ?string $raw, bool $required): ?string
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', preg_split('/[\s,]+/', (string) $raw)),
            fn (int $id) => $id > 0,
        )));

        if ($ids === []) {
            if ($required) {
                throw new SmmException('At least one order ID is required');
            }

            return null;
        }

        if (count($ids) > 50) {
            throw new SmmException('Maximum :max orders per ticket', ['max' => 50]);
        }

        $owned = $user->orders()->whereIn('id', $ids)->pluck('id')->all();
        $missing = array_diff($ids, $owned);

        if ($missing !== []) {
            throw new SmmException('Order not found: :ids', ['ids' => implode(', ', $missing)]);
        }

        return implode(',', $ids);
    }
}
