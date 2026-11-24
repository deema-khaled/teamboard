<?php

namespace App\Http\Resources;

use App\Models\Board;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Board */
class BoardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'is_owner' => $this->owner_id === $request->user()?->id,
            'cards_count' => $this->whenCounted('cards'),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($m) => [
                ...$m->toPublic(),
                'role' => $m->pivot->role,
            ])),
            'columns' => $this->whenLoaded('columns', fn () => $this->columns->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'position' => $c->position,
                'cards' => $c->relationLoaded('cards') ? CardResource::collection($c->cards) : [],
            ])),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
