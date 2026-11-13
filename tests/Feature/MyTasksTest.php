<?php

namespace Tests\Feature;

use App\Enums\AccessLevel;
use App\Models\Membership;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MyTasksTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private TaskList $list;

    protected function setUp(): void
    {
        parent::setUp();

        // A Wednesday; the week runs until Sunday the 15th.
        $this->travelTo('2026-11-11 09:00');
        $this->user = User::factory()->create();
        $this->list = TaskList::factory()->create();
    }

    private function task(string $title, ?string $start, ?string $due, bool $done = false, bool $mine = true): Task
    {
        $task = Task::factory()->for($this->list)->create([
            'title' => $title,
            'start_date' => $start,
            'due_date' => $due,
            'status_id' => $this->list->statuses()->where('is_done', $done)->value('id'),
        ]);
        if ($mine) {
            $task->assignees()->attach($this->user);
        }

        return $task;
    }

    private function buckets(): array
    {
        $buckets = [];
        $this->actingAs($this->user)->get(route('dashboard'))->assertInertia(function (AssertableInertia $page) use (&$buckets) {
            $buckets = collect($page->toArray()['props']['myTasks'])->map(fn ($tasks) => collect($tasks)->pluck('title')->all())->all();
        });

        return $buckets;
    }

    public function test_tasks_are_sorted_into_when_they_are_due()
    {
        $this->task('Gestern fällig', null, '2026-11-10');
        $this->task('Zweitägig ab heute', '2026-11-11', '2026-11-12');
        $this->task('Läuft seit Montag', '2026-11-09', '2026-11-13');
        $this->task('Freitag', null, '2026-11-13');
        $this->task('Startet Samstag', '2026-11-14', '2026-11-20');
        $this->task('Nächste Woche', null, '2026-11-18');
        $this->task('Irgendwann', null, null);

        $this->assertSame([
            'overdue' => ['Gestern fällig'],
            'today' => ['Zweitägig ab heute', 'Läuft seit Montag'],
            'week' => ['Freitag', 'Startet Samstag'],
            'later' => ['Nächste Woche'],
            'undated' => ['Irgendwann'],
        ], $this->buckets());
    }

    public function test_done_tasks_and_tasks_of_others_are_left_out()
    {
        $this->task('Erledigt', null, '2026-11-11', done: true);
        $this->task('Von jemand anderem', null, '2026-11-11', mine: false);

        $this->assertSame([], array_merge(...array_values($this->buckets())));
    }

    public function test_clients_only_see_tasks_of_lists_they_can_still_open()
    {
        $client = User::factory()->client()->create();
        $membership = Membership::create(['user_id' => $client->id, 'task_list_id' => $this->list->id, 'level' => AccessLevel::Edit]);
        $this->task('Mix', null, '2026-11-11', mine: false)->assignees()->attach($client);
        $this->user = $client;

        $this->assertSame(['Mix'], $this->buckets()['today']);

        $membership->delete();
        $client->refreshAccess();
        $this->assertSame([], $this->buckets()['today']);
    }
}
