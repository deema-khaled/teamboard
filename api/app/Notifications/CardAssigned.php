<?php

namespace App\Notifications;

use App\Models\Card;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class CardAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Card $card, public User $by) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'card.assigned',
            'message' => "{$this->by->name} assigned you \"{$this->card->title}\"",
            'board_id' => $this->card->board_id,
            'card_id' => $this->card->id,
            'actor' => $this->by->toPublic(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
