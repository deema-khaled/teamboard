<?php

namespace App\Models;

use App\Enums\BoardRole;
use Database\Factories\BoardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Board extends Model
{
    /** @use HasFactory<BoardFactory> */
    use HasFactory;

    public const COLORS = ['indigo', 'sky', 'emerald', 'amber', 'rose', 'violet', 'slate'];

    public const DEFAULT_COLUMNS = ['To do', 'In progress', 'Review', 'Done'];

    protected $fillable = ['name', 'description', 'color'];

    protected static function booted(): void
    {
        // The creator becomes the owner and the board starts with sensible columns.
        static::created(function (Board $board) {
            $board->members()->attach($board->owner_id, ['role' => BoardRole::Owner->value]);

            foreach (self::DEFAULT_COLUMNS as $i => $name) {
                $board->columns()->create(['name' => $name, 'position' => $i]);
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    /** @return HasMany<Column, $this> */
    public function columns(): HasMany
    {
        return $this->hasMany(Column::class)->orderBy('position');
    }

    /** @return HasMany<Card, $this> */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest('created_at')->latest('id');
    }

    /** @return HasMany<Invitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function log(?User $actor, string $type, string $description): Activity
    {
        return $this->activities()->create([
            'user_id' => $actor?->id,
            'type' => $type,
            'description' => $description,
        ]);
    }
}
