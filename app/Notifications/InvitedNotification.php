<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invites someone without an account to a project or list.
 */
class InvitedNotification extends Notification
{
    public function __construct(private Invitation $invitation, private string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $target = $this->invitation->target();
        $inviter = $this->invitation->inviter?->name ?? config('app.name');

        return (new MailMessage)
            ->subject(__(':name invited you to ":target"', ['name' => $inviter, 'target' => $target->name]))
            ->greeting(__('Hello!'))
            ->line(__(':name invited you to ":target" in :app.', ['name' => $inviter, 'target' => $target->name, 'app' => config('app.name')]))
            ->line(__('Your access: :level', ['level' => $this->invitation->level->label()]))
            ->action(__('Accept invitation'), route('invitations.show', $this->token))
            ->line(__('The invitation is valid for :days days. If you were not expecting it, you can ignore this email.', ['days' => Invitation::VALID_DAYS]));
    }
}
