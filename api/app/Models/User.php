<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return BelongsToMany<Board, $this> */
    public function boards(): BelongsToMany
    {
        return $this->belongsToMany(Board::class)->withPivot('role')->withTimestamps();
    }

    /** Initials and a stable colour for avatars in the SPA. @return array<string, mixed> */
    public function toPublic(): array
    {
        $palette = ['indigo', 'sky', 'emerald', 'amber', 'rose', 'violet', 'teal', 'orange'];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'initials' => collect(explode(' ', $this->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode(''),
            'color' => $palette[$this->id % count($palette)],
        ];
    }
}
