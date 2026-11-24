<?php

namespace App\Events;

use App\Models\Board;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * One event for everything that happens on a board. The SPA switches on
 * `type` (card.created, card.moved, column.renamed, …) and patches its state.
 *
 * Sent on the board's presence channel, so the same subscription also tells
 * the SPA who else is looking at the board right now. Dispatched with
 * ->toOthers(), so the person who made the change doesn't get it back.
 */
class BoardChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param  array<string, mixed>  $data */
    public function __construct(
        public Board $board,
        public string $type,
        public array $data,
        public ?User $actor = null,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel("board.{$this->board->id}");
    }

    public function broadcastAs(): string
    {
        return 'board.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
            'actor' => $this->actor?->toPublic(),
        ];
    }
}
