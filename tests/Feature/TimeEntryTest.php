<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TimeEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_a_timer_stops_the_one_already_running()
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $running = TimeEntry::factory()->running()->for($user)->create(['started_at' => now()->subMinutes(30)]);
        $task = Task::factory()->create();

        $this->actingAs($user)->post(route('timer.start', $task));

        $this->assertSame(1800, $running->fresh()->seconds);
        $this->assertSame($task->id, $user->timeEntries()->running()->sole()->task_id);
    }

    public function test_stopping_the_timer_records_the_elapsed_time()
    {
        $user = User::factory()->create();
        $this->freezeTime();
        $entry = TimeEntry::factory()->running()->for($user)->create(['started_at' => now()->subMinutes(90)]);

        $this->actingAs($user)->delete(route('timer.stop'));

        $this->assertSame(5400, $entry->fresh()->seconds);
        $this->assertNotNull($entry->fresh()->ended_at);
    }

    public function test_the_running_timer_is_shared_with_every_page()
    {
        $user = User::factory()->create();
        $entry = TimeEntry::factory()->running()->for($user)->create();

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('timer.task_id', $entry->task_id)
            ->where('timer.task_title', $entry->task->title));
    }

    public function test_time_can_be_added_by_hand()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();

        $this->actingAs($user)->post(route('tasks.time-entries.store', $task), [
            'date' => '2026-11-10',
            'minutes' => 90,
            'note' => 'Arrangement',
        ])->assertSessionHasNoErrors();

        $entry = TimeEntry::sole();
        $this->assertSame(5400, $entry->seconds);
        $this->assertSame('2026-11-10', $entry->started_at->toDateString());
        $this->assertSame('Arrangement', $entry->note);
    }

    public function test_a_manual_entry_needs_a_duration()
    {
        $task = Task::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('tasks.time-entries.store', $task), ['date' => '2026-11-10', 'minutes' => 0])
            ->assertSessionHasErrors('minutes');
    }

    public function test_entries_can_be_changed_and_deleted()
    {
        $entry = TimeEntry::factory()->create();
        $owner = User::factory()->create();

        $this->actingAs($owner)->patch(route('time-entries.update', $entry), ['date' => '2026-11-11', 'minutes' => 15]);
        $this->assertSame(900, $entry->fresh()->seconds);

        $this->actingAs($owner)->delete(route('time-entries.destroy', $entry));
        $this->assertModelMissing($entry);
    }

    public function test_clients_cannot_track_time_without_access()
    {
        $task = Task::factory()->create();

        $this->actingAs(User::factory()->client()->create())
            ->post(route('timer.start', $task))
            ->assertForbidden();
    }

    public function test_lists_show_tracked_time_per_task()
    {
        $task = Task::factory()->create();
        TimeEntry::factory()->for($task)->create(['seconds' => 600]);
        TimeEntry::factory()->for($task)->create(['seconds' => 1200]);
        TimeEntry::factory()->running()->for($task)->create();

        $this->actingAs(User::factory()->create())
            ->get(route('lists.show', $task->taskList))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('showsTime', true)
                ->where('tasks.0.tracked_seconds', 1800)
                ->count('timeEntries', 2));
    }

    public function test_projects_add_up_the_time_of_their_subprojects()
    {
        $project = Project::factory()->create();
        $subproject = Project::factory()->create(['parent_id' => $project->id]);
        TimeEntry::factory()->for(Task::factory()->for(TaskList::factory()->for($project)))->create(['seconds' => 600]);
        TimeEntry::factory()->for(Task::factory()->for(TaskList::factory()->for($subproject)))->create(['seconds' => 300]);

        $this->actingAs(User::factory()->create())
            ->get(route('projects.show', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('trackedSeconds', 900));
    }

    public function test_time_is_hidden_from_clients_unless_the_project_shows_it()
    {
        $project = Project::factory()->create();
        $client = User::factory()->client()->create();

        $this->assertFalse($project->showsTimeTo($client));

        $project->update(['client_can_see_time' => true]);
        $this->assertTrue($project->showsTimeTo($client));
    }
}
