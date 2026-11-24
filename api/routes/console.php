<?php

use Illuminate\Support\Facades\Schedule;

// Remind assignees about cards due tomorrow.
Schedule::command('cards:remind-due')->dailyAt('09:00')->withoutOverlapping();

// Housekeeping.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
