<?php

namespace App\Http\Resources;

use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Card */
class CardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'column_id' => $this->column_id,
            'title' => $this->title,
            'description' => $this->description,
            'label' => $this->label,
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => $this->due_date?->isPast() && ! $this->due_date->isToday(),
            'position' => $this->position,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee?->toPublic()),
            'comments_count' => $this->whenCounted('comments'),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
