<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_tasks_start_in_the_first_open_status()
    {
        $list = TaskList::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('lists.tasks.store', $list), ['title' => 'Beat bauen']);

        $this->assertSame('Offen', Task::sole()->status->name);
    }

    public function test_moving_a_task_to_a_done_status_completes_it()
    {
        $task = Task::factory()->create();
        $done = $task->taskList->statuses()->firstWhere('is_done', true);

        $this->actingAs(User::factory()->create())
            ->patch(route('tasks.update', $task), ['status_id' => $done->id]);

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_reopening_a_task_clears_its_completion()
    {
        $task = Task::factory()->create();
        $statuses = $task->taskList->statuses;
        $task->update(['status_id' => $statuses->firstWhere('is_done', true)->id]);

        $this->actingAs(User::factory()->create())
            ->patch(route('tasks.update', $task), ['status_id' => $statuses->first()->id]);

        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_a_task_cannot_use_a_status_of_another_list()
    {
        $task = Task::factory()->create();
        $foreignStatus = TaskList::factory()->create()->statuses()->first();

        $this->actingAs(User::factory()->create())
            ->patch(route('tasks.update', $task), ['status_id' => $foreignStatus->id])
            ->assertSessionHasErrors('status_id');
    }

    public function test_owner_can_set_start_and_end_dates()
    {
        $task = Task::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('tasks.update', $task), ['start_date' => '2026-12-01', 'due_date' => '2026-12-05']);

        $this->assertSame('2026-12-05', $task->fresh()->due_date->toDateString());
    }

    public function test_the_end_date_cannot_be_before_the_start_date()
    {
        $task = Task::factory()->create(['start_date' => '2026-12-10']);

        $this->actingAs(User::factory()->create())
            ->patch(route('tasks.update', $task), ['due_date' => '2026-12-05'])
            ->assertSessionHasErrors('due_date');
    }

    public function test_a_task_can_be_moved_after_another_task()
    {
        $list = TaskList::factory()->create();
        [$a, $b, $c] = Task::factory(3)->for($list)->sequence(fn ($sequence) => ['position' => $sequence->index])->create();

        $this->actingAs(User::factory()->create())
            ->put(route('tasks.move', $a), ['after_id' => $c->id]);

        $this->assertSame([$b->id, $c->id, $a->id], $list->tasks()->pluck('id')->all());
    }

    public function test_a_task_can_be_moved_before_another_task()
    {
        $list = TaskList::factory()->create();
        [$a, $b, $c] = Task::factory(3)->for($list)->sequence(fn ($sequence) => ['position' => $sequence->index])->create();

        $this->actingAs(User::factory()->create())
            ->put(route('tasks.move', $c), ['before_id' => $a->id]);

        $this->assertSame([$c->id, $a->id, $b->id], $list->tasks()->pluck('id')->all());
    }

    public function test_moving_a_task_into_a_done_column_completes_it()
    {
        $task = Task::factory()->create();
        $done = $task->taskList->statuses()->firstWhere('is_done', true);

        $this->actingAs(User::factory()->create())
            ->put(route('tasks.move', $task), ['status_id' => $done->id]);

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_a_task_cannot_be_moved_next_to_a_task_of_another_list()
    {
        $task = Task::factory()->create();
        $foreignTask = Task::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('tasks.move', $task), ['after_id' => $foreignTask->id])
            ->assertSessionHasErrors('after_id');
    }

    public function test_owner_can_delete_a_task()
    {
        $task = Task::factory()->create();

        $this->actingAs(User::factory()->create())->delete(route('tasks.destroy', $task));

        $this->assertModelMissing($task);
    }

    public function test_clients_cannot_edit_tasks()
    {
        $task = Task::factory()->create();

        $this->actingAs(User::factory()->client()->create())
            ->patch(route('tasks.update', $task), ['title' => 'Fremd'])
            ->assertForbidden();
    }
}
