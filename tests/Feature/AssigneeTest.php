<?php

namespace Tests\Feature;

use App\Enums\AccessLevel;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AssigneeTest extends TestCase
{
    use RefreshDatabase;

    private function clientWith(AccessLevel $level, Project|TaskList $target): User
    {
        $client = User::factory()->client()->create();
        Membership::create([
            'user_id' => $client->id,
            $target instanceof Project ? 'project_id' : 'task_list_id' => $target->id,
            'level' => $level,
        ]);

        return $client;
    }

    public function test_tasks_can_have_several_assignees()
    {
        $owner = User::factory()->create();
        $task = Task::factory()->create();
        $client = $this->clientWith(AccessLevel::View, $task->taskList);

        $this->actingAs($owner)
            ->patch(route('tasks.update', $task), ['assignee_ids' => [$owner->id, $client->id]])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing([$owner->id, $client->id], $task->assignees()->pluck('users.id')->all());
        $this->get(route('lists.show', $task->task_list_id))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tasks.0.assignee_ids', fn ($ids) => collect($ids)->sort()->values()->all() === collect([$owner->id, $client->id])->sort()->values()->all()));
    }

    public function test_the_people_of_a_list_include_those_with_access_from_a_parent_project()
    {
        $owner = User::factory()->create(['name' => 'Anna Owner']);
        $project = Project::factory()->create();
        $list = TaskList::factory()->for(Project::factory()->create(['parent_id' => $project->id]))->create();
        $fromProject = $this->clientWith(AccessLevel::View, $project);
        $fromList = $this->clientWith(AccessLevel::Edit, $list);
        $this->clientWith(AccessLevel::Edit, TaskList::factory()->create());

        $this->assertEqualsCanonicalizing([$owner->id, $fromProject->id, $fromList->id], $list->people()->modelKeys());
    }

    public function test_only_people_with_access_can_be_assigned()
    {
        $task = Task::factory()->create();
        $stranger = $this->clientWith(AccessLevel::Edit, TaskList::factory()->create());

        $this->actingAs(User::factory()->create())
            ->patch(route('tasks.update', $task), ['assignee_ids' => [$stranger->id]])
            ->assertSessionHasErrors('assignee_ids.0');
        $this->assertSame(0, $task->assignees()->count());
    }

    public function test_assignees_can_be_cleared()
    {
        $owner = User::factory()->create();
        $task = Task::factory()->create();
        $task->assignees()->attach($owner);

        $this->actingAs($owner)->patch(route('tasks.update', $task), ['assignee_ids' => []]);

        $this->assertSame(0, $task->assignees()->count());
    }

    public function test_viewers_cannot_change_assignees()
    {
        $task = Task::factory()->create();
        $viewer = $this->clientWith(AccessLevel::View, $task->taskList);

        $this->actingAs($viewer)->patch(route('tasks.update', $task), ['assignee_ids' => [$viewer->id]])->assertForbidden();
    }
}
