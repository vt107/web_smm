<?php

namespace App\Http\Resources;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Ticket */
class TicketResource extends JsonResource
{
    use Presenter;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'category' => $this->category->value,
            'category_label' => $this->category->getLabel(),
            'order_ids' => $this->order_ids,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'status_color' => $this->status->getColor(),
            'is_closed' => $this->status === TicketStatus::Closed,
            'last_reply_at' => $this->time($request, $this->last_reply_at),
            'created_at' => $this->time($request, $this->created_at),
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn ($m) => [
                'id' => $m->id,
                'is_staff' => $m->is_staff,
                'author' => $m->is_staff ? 'Support' : 'You',
                'message' => $m->message,
                'attachments' => array_map(fn ($p) => Storage::disk('public')->url($p), $m->attachments ?? []),
                'created_at' => $this->time($request, $m->created_at),
            ])),
        ];
    }
}
