<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DigestNotification;
use App\Support\Branding;
use App\Support\MyTasks;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Emails subscribers what is due for them today or this week. Nobody gets
 * an email without tasks, and nobody gets the same digest twice a day.
 */
class SendDigests extends Command
{
    protected $signature = 'projektor:send-digests {period : daily or weekly}';

    protected $description = 'Send the daily or weekly task emails';

    public function handle(): int
    {
        $period = $this->argument('period');
        if (! in_array($period, [DigestNotification::DAILY, DigestNotification::WEEKLY], true)) {
            $this->error('The period must be "daily" or "weekly".');

            return self::INVALID;
        }

        Branding::apply();
        $today = CarbonImmutable::today();
        $sentOn = "digest_{$period}_sent_on";
        $sent = 0;

        User::where("digest_{$period}", true)
            ->where(fn ($query) => $query->whereNull($sentOn)->orWhere($sentOn, '<', $today->toDateString()))
            // On Mondays the weekly email already lists today's tasks.
            ->when($period === DigestNotification::DAILY && $today->isMonday(), fn ($query) => $query->where('digest_weekly', false))
            ->each(function (User $user) use ($period, $today, $sentOn, &$sent) {
                $tasks = new MyTasks($user, $today);
                $groups = $period === DigestNotification::DAILY ? $tasks->forToday() : $tasks->forThisWeek();
                if ($groups->isEmpty()) {
                    return;
                }

                try {
                    $user->notify(new DigestNotification($period, $groups, $today));
                    $user->forceFill([$sentOn => $today])->save();
                    $sent++;
                } catch (Throwable $exception) {
                    // One failing address must not stop the others.
                    report($exception);
                }
            });

        $this->info("Sent {$sent} {$period} digest(s).");

        return self::SUCCESS;
    }
}
