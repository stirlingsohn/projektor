<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskListTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_list_with_default_statuses()
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('projects.lists.store', $project), ['name' => 'Mixing']);

        $this->assertSame(
            ['Offen', 'In Bearbeitung', 'In Review', 'Erledigt'],
            TaskList::sole()->statuses->pluck('name')->all(),
        );
    }

    public function test_owner_can_view_a_list_with_its_tasks()
    {
        $task = Task::factory()->create(['title' => 'Vocals aufnehmen', 'due_date' => '2026-12-24']);

        $this->actingAs(User::factory()->create())
            ->get(route('lists.show', $task->task_list_id))
            ->assertInertia(fn (Assert $page) => $page
                ->component('lists/Show')
                ->where('tasks.0.title', 'Vocals aufnehmen')
                ->where('tasks.0.due_date', '2026-12-24')
                ->has('statuses', 4));
    }

    public function test_lists_are_shared_for_the_sidebar()
    {
        TaskList::factory()->create(['name' => 'Mastering']);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('lists.0.name', 'Mastering'));
    }

    public function test_owner_can_rename_a_list()
    {
        $list = TaskList::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('lists.update', $list), ['name' => 'Neu']);

        $this->assertSame('Neu', $list->fresh()->name);
    }

    public function test_deleting_a_list_deletes_its_tasks()
    {
        $task = Task::factory()->create();
        $projectId = $task->taskList->project_id;

        $this->actingAs(User::factory()->create())
            ->delete(route('lists.destroy', $task->task_list_id))
            ->assertRedirect(route('projects.show', $projectId));

        $this->assertModelMissing($task);
    }

    public function test_clients_cannot_view_lists()
    {
        $list = TaskList::factory()->create();

        $this->actingAs(User::factory()->client()->create())
            ->get(route('lists.show', $list))
            ->assertForbidden();
    }
}
