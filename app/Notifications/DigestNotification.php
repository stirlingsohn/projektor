<?php

namespace App\Notifications;

use App\Models\Task;
use App\Support\MyTasks;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * The tasks someone has for today or this week, sent by subscription.
 */
class DigestNotification extends Notification
{
    public const DAILY = 'daily';

    public const WEEKLY = 'weekly';

    /**
     * @param  Collection<string, Collection<int, Task>>  $groups  Non-empty buckets from {@see MyTasks}.
     */
    public function __construct(private string $period, private Collection $groups, private CarbonImmutable $today) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->period === self::DAILY
            ? __('Your tasks for :date', ['date' => $this->today->isoFormat('dddd, D. MMMM')])
            : __('Your tasks this week (:from – :to)', [
                'from' => $this->today->isoFormat('D. MMM'),
                'to' => $this->today->endOfWeek()->isoFormat('D. MMM'),
            ]);

        return (new MailMessage)
            ->subject($subject)
            ->markdown('mail.digest', [
                'heading' => $subject,
                'groups' => $this->groups->mapWithKeys(fn (Collection $tasks, string $bucket) => [
                    $this->label($bucket) => $tasks->map(fn (Task $task) => $this->line($task)),
                ]),
            ]);
    }

    private function label(string $bucket): string
    {
        return match ($bucket) {
            'overdue' => __('Overdue'),
            'today' => __('Today'),
            default => __('Later this week'),
        };
    }

    /**
     * @return array{title: string, url: string, where: string, dates: ?string}
     */
    private function line(Task $task): array
    {
        $start = $task->start_date;
        $due = $task->due_date;
        $format = fn ($date) => $date->isoFormat('D. MMM');

        return [
            'title' => self::escape($task->title),
            'url' => route('lists.show', ['list' => $task->task_list_id, 'task' => $task->id]),
            'where' => self::escape($task->taskList->project->name.' · '.$task->taskList->name),
            'dates' => match (true) {
                $start && $due && ! $start->eq($due) => $format($start).' – '.$format($due),
                (bool) ($due ?? $start) => $format($due ?? $start),
                default => null,
            },
        ];
    }

    /** Task titles are plain text; keep them from turning into Markdown. */
    private static function escape(string $text): string
    {
        return addcslashes($text, '\\`*_[]<>#|');
    }
}
