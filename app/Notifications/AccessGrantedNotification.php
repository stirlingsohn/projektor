<?php

namespace App\Notifications;

use App\Enums\AccessLevel;
use App\Models\Project;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells someone who already has an account about a newly shared project or list.
 */
class AccessGrantedNotification extends Notification
{
    public function __construct(private Project|TaskList $target, private AccessLevel $level, private ?User $inviter) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inviter = $this->inviter?->name ?? config('app.name');
        $url = $this->target instanceof Project ? route('projects.show', $this->target) : route('lists.show', $this->target);

        return (new MailMessage)
            ->subject(__(':name shared ":target" with you', ['name' => $inviter, 'target' => $this->target->name]))
            ->greeting(__('Hello :name!', ['name' => $notifiable->name]))
            ->line(__(':name shared ":target" with you.', ['name' => $inviter, 'target' => $this->target->name]))
            ->line(__('Your access: :level', ['level' => $this->level->label()]))
            ->action(__('Open ":target"', ['target' => $this->target->name]), $url);
    }
}
