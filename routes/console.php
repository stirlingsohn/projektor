<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('projektor:send-digests daily')->dailyAt('07:00');
Schedule::command('projektor:send-digests weekly')->mondays()->at('07:00');
// Deleted tasks and old undo steps are removed for good after 30 days.
Schedule::command('model:prune')->dailyAt('03:00');
