<?php

namespace App\Notifications;

use App\Models\Card;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CardDueSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Card $card) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Due tomorrow: {$this->card->title}")
            ->greeting("Hi {$notifiable->name},")
            ->line("\"{$this->card->title}\" on the board {$this->card->board->name} is due tomorrow.")
            ->action('Open the board', rtrim(config('app.frontend_url'), '/')."/boards/{$this->card->board_id}?card={$this->card->id}");
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'card.due_soon',
            'message' => "\"{$this->card->title}\" is due tomorrow",
            'board_id' => $this->card->board_id,
            'card_id' => $this->card->id,
            'actor' => null,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
