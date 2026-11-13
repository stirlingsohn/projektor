<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Notifications\DigestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DigestTest extends TestCase
{
    use RefreshDatabase;

    private TaskList $list;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo('2026-11-11 07:00'); // a Wednesday
        $this->list = TaskList::factory()->create();
    }

    private function subscriber(bool $daily = true, bool $weekly = false): User
    {
        return User::factory()->create(['digest_daily' => $daily, 'digest_weekly' => $weekly]);
    }

    private function assign(User $user, ?string $start, ?string $due, string $title = 'Mix'): Task
    {
        $task = Task::factory()->for($this->list)->create(['title' => $title, 'start_date' => $start, 'due_date' => $due]);
        $task->assignees()->attach($user);

        return $task;
    }

    public function test_the_daily_email_lists_todays_and_overdue_tasks()
    {
        $user = $this->subscriber();
        $this->assign($user, '2026-11-11', '2026-11-12', 'Zweitägig ab heute');
        $this->assign($user, null, '2026-11-09', 'Überfällig');
        $this->assign($user, null, '2026-11-13', 'Freitag');

        $this->artisan('projektor:send-digests daily')->assertSuccessful();

        Notification::assertSentTo($user, DigestNotification::class, function (DigestNotification $notification) use ($user) {
            $html = (string) $notification->toMail($user)->render();

            return str_contains($html, 'Zweitägig ab heute') && str_contains($html, 'Überfällig') && ! str_contains($html, 'Freitag')
                && str_contains($html, route('lists.show', ['list' => $this->list->id, 'task' => Task::firstWhere('title', 'Zweitägig ab heute')->id]));
        });
    }

    public function test_the_weekly_email_lists_the_rest_of_the_week()
    {
        $user = $this->subscriber(daily: false, weekly: true);
        $this->assign($user, null, '2026-11-13', 'Freitag');
        $this->assign($user, '2026-11-14', '2026-11-20', 'Startet Samstag');
        $this->assign($user, null, '2026-11-18', 'Nächste Woche');

        $this->artisan('projektor:send-digests weekly');

        Notification::assertSentTo($user, DigestNotification::class, function (DigestNotification $notification) use ($user) {
            $html = (string) $notification->toMail($user)->render();

            return str_contains($html, 'Freitag') && str_contains($html, 'Startet Samstag') && ! str_contains($html, 'Nächste Woche');
        });
    }

    public function test_nothing_is_sent_without_tasks_or_subscription()
    {
        $idle = $this->subscriber();
        $this->assign($idle, null, '2026-11-20');
        $unsubscribed = $this->subscriber(daily: false);
        $this->assign($unsubscribed, null, '2026-11-11');

        $this->artisan('projektor:send-digests daily');

        Notification::assertNothingSent();
    }

    public function test_a_digest_goes_out_only_once_a_day()
    {
        $user = $this->subscriber();
        $this->assign($user, null, '2026-11-11');

        $this->artisan('projektor:send-digests daily');
        $this->artisan('projektor:send-digests daily');

        Notification::assertSentToTimes($user, DigestNotification::class, 1);
    }

    public function test_weekly_subscribers_get_no_extra_daily_email_on_mondays()
    {
        $this->travelTo('2026-11-16 07:00');
        $both = $this->subscriber(daily: true, weekly: true);
        $this->assign($both, null, '2026-11-16');

        $this->artisan('projektor:send-digests daily');
        $this->artisan('projektor:send-digests weekly');

        Notification::assertSentToTimes($both, DigestNotification::class, 1);
    }

    public function test_task_titles_cannot_inject_links()
    {
        $user = $this->subscriber();
        $this->assign($user, null, '2026-11-11', '[Klick](https://evil.example)');

        $this->artisan('projektor:send-digests daily');

        Notification::assertSentTo($user, DigestNotification::class, fn (DigestNotification $notification) => ! str_contains((string) $notification->toMail($user)->render(), 'href="https://evil.example"'));
    }

    public function test_users_subscribe_in_their_settings()
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user)->put(route('digests.update'), ['digest_daily' => true, 'digest_weekly' => false])->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->digest_daily);
        $this->assertFalse($user->fresh()->digest_weekly);
    }
}
