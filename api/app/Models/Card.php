<?php

namespace App\Models;

use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory;

    public const LABELS = ['red', 'orange', 'yellow', 'green', 'blue', 'purple'];

    protected $fillable = ['title', 'description', 'label', 'due_date', 'assignee_id', 'position'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'position' => 'integer',
            'reminded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Board, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /** @return BelongsTo<Column, $this> */
    public function column(): BelongsTo
    {
        return $this->belongsTo(Column::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->oldest();
    }
}
