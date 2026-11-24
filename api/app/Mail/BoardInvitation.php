<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BoardInvitation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation, public string $token) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->invitation->inviter->name} invited you to \"{$this->invitation->board->name}\"");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.board-invitation', with: [
            'inviter' => $this->invitation->inviter->name,
            'board' => $this->invitation->board->name,
            'url' => rtrim(config('app.frontend_url'), '/')."/invitations/{$this->token}",
            'expires' => $this->invitation->expires_at->format('j M Y'),
        ]);
    }
}
