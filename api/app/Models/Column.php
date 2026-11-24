<?php

namespace App\Models;

use Database\Factories\ColumnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Column extends Model
{
    /** @use HasFactory<ColumnFactory> */
    use HasFactory;

    protected $fillable = ['name', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /** @return BelongsTo<Board, $this> */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /** @return HasMany<Card, $this> */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class)->orderBy('position');
    }
}
