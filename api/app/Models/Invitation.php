<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    protected $fillable = ['email', 'invited_by', 'token_hash', 'expires_at'];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Creates an invitation and returns it with the plain token, which is
     * only ever sent in the email. The database keeps a SHA-256 hash.
     *
     * @return array{0: Invitation, 1: string}
     */
    public static function issue(Board $board, User $inviter, string $email): array
    {
        $token = Str::random(48);

        $invitation = $board->invitations()->create([
            'email' => strtolower($email),
            'invited_by' => $inviter->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);

        return [$invitation, $token];
    }

    /** @param  Builder<Invitation>  $query */
    public function scopeForToken(Builder $query, string $token): void
    {
        $query->where('token_hash', hash('sha256', $token));
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    /** @return BelongsTo<Board, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
