<?php

namespace Tests\Feature;

use App\Enums\AccessLevel;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProjectDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function task(TaskList $list, array $attributes = [], bool $done = false): Task
    {
        return Task::factory()->for($list)->create([
            ...$attributes,
            'status_id' => $list->statuses()->where('is_done', $done)->value('id'),
        ]);
    }

    public function test_lists_show_their_progress()
    {
        $this->travelTo('2026-11-13 10:00');
        $project = Project::factory()->create();
        $list = TaskList::factory()->for($project)->create();
        $this->task($list, ['due_date' => '2026-11-10']);
        $this->task($list, ['due_date' => '2026-11-20']);
        $this->task($list, ['due_date' => '2026-11-01'], done: true);

        $this->actingAs(User::factory()->create())->get(route('projects.show', $project))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary', ['open' => 2, 'overdue' => 1, 'done' => 1, 'total' => 3])
            ->where("listStats.{$list->id}", ['total' => 3, 'done' => 1, 'overdue' => 1, 'next_due' => '2026-11-10']));
    }

    public function test_upcoming_tasks_include_subprojects_and_leave_out_done_and_undated_ones()
    {
        $project = Project::factory()->create();
        $subproject = Project::factory()->create(['parent_id' => $project->id, 'name' => 'EP']);
        $list = TaskList::factory()->for($project)->create();
        $subList = TaskList::factory()->for($subproject)->create(['name' => 'Mix']);
        $later = $this->task($list, ['due_date' => '2026-12-01']);
        $sooner = $this->task($subList, ['due_date' => '2026-11-15']);
        $this->task($list, ['due_date' => '2026-11-14'], done: true);
        $this->task($list);

        $this->actingAs(User::factory()->create())->get(route('projects.show', $project))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('upcoming.0.id', $sooner->id)
            ->where('upcoming.0.list', ['id' => $subList->id, 'name' => 'Mix', 'project' => 'EP'])
            ->where('upcoming.1.id', $later->id)
            ->has('upcoming', 2));
    }

    public function test_time_is_split_by_list()
    {
        $this->travelTo('2026-11-13 10:00');
        $project = Project::factory()->create();
        [$recording, $mixing] = TaskList::factory()->for($project)->count(2)->create();
        TimeEntry::factory()->for($this->task($recording))->create(['seconds' => 600, 'started_at' => '2026-11-12 09:00', 'ended_at' => '2026-11-12 09:10']);
        TimeEntry::factory()->for($this->task($mixing))->create(['seconds' => 1800, 'started_at' => '2026-11-02 09:00', 'ended_at' => '2026-11-02 09:30']);

        $this->actingAs(User::factory()->create())->get(route('projects.show', $project))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('time.total', 2400)
            ->where('time.week', 600)
            ->where('time.lists.0.id', $mixing->id)
            ->where('time.lists.1.seconds', 600));
    }

    public function test_clients_only_see_tasks_of_lists_shared_with_them_and_no_hidden_time()
    {
        $project = Project::factory()->create();
        $shared = TaskList::factory()->for($project)->create();
        $other = TaskList::factory()->for($project)->create();
        $this->task($shared, ['due_date' => '2026-11-20']);
        $this->task($other, ['due_date' => '2026-11-18']);
        $client = User::factory()->client()->create();
        Membership::create(['user_id' => $client->id, 'task_list_id' => $shared->id, 'level' => AccessLevel::View]);

        $this->actingAs($client)->get(route('projects.show', $project))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary.total', 1)
            ->has('upcoming', 1)
            ->where('upcoming.0.list.id', $shared->id)
            ->missing("listStats.{$other->id}")
            ->where('time', null));
    }
}
