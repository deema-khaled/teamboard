<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Column;
use Illuminate\Support\Facades\DB;

/**
 * Keeps card positions as a dense 0..n sequence per column.
 *
 * Moving a card rewrites the positions of the column(s) involved inside one
 * transaction, with the rows locked, so two people dragging at the same time
 * can't leave duplicate or missing positions.
 */
class CardMover
{
    /** @return array<int, list<int>> column id => ordered card ids, for every touched column */
    public function move(Card $card, Column $target, int $position): array
    {
        return DB::transaction(function () use ($card, $target, $position) {
            $sourceId = $card->column_id;

            // Lock both columns' cards in a stable order to avoid deadlocks.
            Card::whereIn('column_id', array_unique([$sourceId, $target->id]))
                ->orderBy('id')->lockForUpdate()->get(['id']);

            $targetIds = Card::where('column_id', $target->id)
                ->whereKeyNot($card->id)
                ->orderBy('position')->orderBy('id')
                ->pluck('id')->all();

            $position = max(0, min($position, count($targetIds)));
            array_splice($targetIds, $position, 0, [$card->id]);

            $card->forceFill(['column_id' => $target->id])->save();
            $this->resequence($targetIds);

            $orders = [$target->id => $targetIds];

            if ($sourceId !== $target->id) {
                $orders[$sourceId] = $this->resequenceColumn($sourceId);
            }

            return $orders;
        });
    }

    /** Puts a new card at the bottom of its column. */
    public function nextPosition(Column $column): int
    {
        return (int) Card::where('column_id', $column->id)->max('position') + ($column->cards()->exists() ? 1 : 0);
    }

    /** @return list<int> */
    public function resequenceColumn(int $columnId): array
    {
        $ids = Card::where('column_id', $columnId)->orderBy('position')->orderBy('id')->pluck('id')->all();
        $this->resequence($ids);

        return $ids;
    }

    /** @param  list<int>  $ids */
    private function resequence(array $ids): void
    {
        foreach ($ids as $position => $id) {
            Card::whereKey($id)->where('position', '!=', $position)->update(['position' => $position]);
        }
    }
}
