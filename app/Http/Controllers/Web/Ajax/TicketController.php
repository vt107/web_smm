<?php

namespace App\Http\Controllers\Web\Ajax;

use App\Enums\TicketCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    private const ATTACHMENT_RULES = ['attachments' => ['nullable', 'array', 'max:3'], 'attachments.*' => ['image', 'max:2048']];

    public function __construct(private readonly TicketService $tickets) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return TicketResource::collection($request->user()->tickets()->latest('last_reply_at')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'order_ids' => ['nullable', 'string', 'max:500'],
            'message' => ['required', 'string', 'max:5000'],
        ] + self::ATTACHMENT_RULES);

        $ticket = $this->tickets->open(
            $request->user(),
            $data['subject'],
            TicketCategory::from($data['category']),
            $data['order_ids'] ?? null,
            $data['message'],
            $request->file('attachments', []),
        );

        return response()->json(['message' => 'Ticket submitted', 'ticket' => new TicketResource($ticket)], 201);
    }

    public function show(Request $request, int $ticket): TicketResource
    {
        return new TicketResource($this->find($request, $ticket)->load(['messages' => fn ($q) => $q->orderBy('id')]));
    }

    public function reply(Request $request, int $ticket): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:5000']] + self::ATTACHMENT_RULES);

        $this->tickets->reply($this->find($request, $ticket), $request->user(), $data['message'], false, $request->file('attachments', []));

        return response()->json(['message' => 'Reply sent']);
    }

    public function close(Request $request, int $ticket): JsonResponse
    {
        $this->tickets->close($this->find($request, $ticket));

        return response()->json(['message' => 'Ticket closed']);
    }

    private function find(Request $request, int $id): Ticket
    {
        return $request->user()->tickets()->findOrFail($id);
    }
}
