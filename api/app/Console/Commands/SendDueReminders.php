<?php

namespace App\Console\Commands;

use App\Models\Card;
use App\Notifications\CardDueSoon;
use Illuminate\Console\Command;

/**
 * Runs daily (see routes/console.php): reminds assignees of cards due
 * tomorrow. Each card is reminded once, even if the command runs again.
 */
class SendDueReminders extends Command
{
    protected $signature = 'cards:remind-due';

    protected $description = 'Notify assignees about cards that are due tomorrow';

    public function handle(): int
    {
        $sent = 0;

        Card::query()
            ->with(['assignee', 'board', 'column'])
            ->whereDate('due_date', today()->addDay())
            ->whereNotNull('assignee_id')
            ->whereNull('reminded_at')
            ->whereHas('column', fn ($q) => $q->where('name', '!=', 'Done'))
            ->chunkById(100, function ($cards) use (&$sent) {
                foreach ($cards as $card) {
                    $card->assignee->notify(new CardDueSoon($card));
                    $card->forceFill(['reminded_at' => now()])->save();
                    $sent++;
                }
            });

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
