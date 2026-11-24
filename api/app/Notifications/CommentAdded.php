<?php

namespace App\Notifications;

use App\Models\Card;
use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class CommentAdded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Comment $comment, public Card $card) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'comment.added',
            'message' => "{$this->comment->user->name} commented on \"{$this->card->title}\": ".Str::limit($this->comment->body, 60),
            'board_id' => $this->card->board_id,
            'card_id' => $this->card->id,
            'actor' => $this->comment->user->toPublic(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
