<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskDependencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Two tasks in one list: `$second` depends on `$first` when `$linked`.
     *
     * @return array{Task, Task}
     */
    private function tasks(array $first = [], array $second = [], bool $linked = false): array
    {
        $list = TaskList::factory()->create();
        $tasks = [Task::factory()->for($list)->create($first), Task::factory()->for($list)->create($second)];
        if ($linked) {
            $tasks[1]->dependencies()->attach($tasks[0]);
        }

        return $tasks;
    }

    public function test_owner_can_add_a_dependency()
    {
        [$first, $second] = $this->tasks();

        $this->actingAs(User::factory()->create())
            ->post(route('tasks.dependencies.store', $second), ['depends_on_id' => $first->id]);

        $this->assertTrue($second->dependencies()->whereKey($first->id)->exists());
    }

    public function test_dependencies_are_part_of_the_list_page()
    {
        [$first, $second] = $this->tasks(linked: true);

        $this->actingAs(User::factory()->create())
            ->get(route('lists.show', $first->task_list_id))
            ->assertInertia(fn (Assert $page) => $page->where('tasks.1.depends_on', [$first->id]));
    }

    public function test_a_task_cannot_depend_on_itself()
    {
        [$task] = $this->tasks();

        $this->actingAs(User::factory()->create())
            ->post(route('tasks.dependencies.store', $task), ['depends_on_id' => $task->id])
            ->assertSessionHasErrors('depends_on_id');
    }

    public function test_a_task_cannot_depend_on_a_task_of_another_list()
    {
        [$task] = $this->tasks();
        $foreignTask = Task::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('tasks.dependencies.store', $task), ['depends_on_id' => $foreignTask->id])
            ->assertSessionHasErrors('depends_on_id');
    }

    public function test_circular_dependencies_are_rejected()
    {
        [$first, $second] = $this->tasks(linked: true);
        $third = Task::factory()->for($first->taskList)->create();
        $third->dependencies()->attach($second);

        $this->actingAs(User::factory()->create())
            ->post(route('tasks.dependencies.store', $first), ['depends_on_id' => $third->id])
            ->assertSessionHasErrors('depends_on_id');
    }

    public function test_owner_can_remove_a_dependency()
    {
        [$first, $second] = $this->tasks(linked: true);

        $this->actingAs(User::factory()->create())
            ->delete(route('tasks.dependencies.destroy', [$second, $first]));

        $this->assertFalse($second->dependencies()->exists());
    }

    public function test_adding_a_dependency_pushes_the_task_back()
    {
        [$first, $second] = $this->tasks(
            ['start_date' => '2026-12-01', 'due_date' => '2026-12-05'],
            ['start_date' => '2026-12-03', 'due_date' => '2026-12-04'],
        );

        $this->actingAs(User::factory()->create())
            ->post(route('tasks.dependencies.store', $second), ['depends_on_id' => $first->id]);

        $this->assertSame(['2026-12-06', '2026-12-07'], [$second->fresh()->start_date->toDateString(), $second->fresh()->due_date->toDateString()]);
    }

    public function test_moving_a_task_later_pushes_its_dependents_along_a_chain()
    {
        [$first, $second] = $this->tasks(
            ['start_date' => '2026-12-01', 'due_date' => '2026-12-02'],
            ['start_date' => '2026-12-03', 'due_date' => '2026-12-03'],
            linked: true,
        );
        $third = Task::factory()->for($first->taskList)->create(['start_date' => '2026-12-04', 'due_date' => '2026-12-06']);
        $third->dependencies()->attach($second);

        $this->actingAs(User::factory()->create())
            ->patch(route('tasks.update', $first), ['start_date' => '2026-12-08', 'due_date' => '2026-12-09']);

        $this->assertSame(['2026-12-11', '2026-12-13'], [$third->fresh()->start_date->toDateString(), $third->fresh()->due_date->toDateString()]);
    }

    public function test_moving_a_task_earlier_does_not_pull_dependents_forward()
    {
        [$first, $second] = $this->tasks(
            ['start_date' => '2026-12-05', 'due_date' => '2026-12-06'],
            ['start_date' => '2026-12-10', 'due_date' => '2026-12-12'],
            linked: true,
        );

        $this->actingAs(User::factory()->create())
            ->patch(route('tasks.update', $first), ['start_date' => '2026-12-01', 'due_date' => '2026-12-02']);

        $this->assertSame('2026-12-10', $second->fresh()->start_date->toDateString());
    }

    public function test_dependents_without_dates_are_left_alone()
    {
        [$first, $second] = $this->tasks(linked: true);

        $this->actingAs(User::factory()->create())
            ->patch(route('tasks.update', $first), ['start_date' => '2026-12-01', 'due_date' => '2026-12-02']);

        $this->assertNull($second->fresh()->start_date);
    }

    public function test_clients_cannot_add_dependencies()
    {
        [$first, $second] = $this->tasks();

        $this->actingAs(User::factory()->client()->create())
            ->post(route('tasks.dependencies.store', $second), ['depends_on_id' => $first->id])
            ->assertForbidden();
    }
}
