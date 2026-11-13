<?php

namespace Tests\Feature;

use App\Enums\AccessLevel;
use App\Models\Comment;
use App\Models\Membership;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TaskHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private TaskList $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->list = TaskList::factory()->create();
        $this->actingAs($this->owner);
    }

    private function undo()
    {
        return $this->post(route('lists.undo', $this->list));
    }

    private function redo()
    {
        return $this->post(route('lists.redo', $this->list));
    }

    public function test_edits_can_be_undone_and_redone()
    {
        $task = Task::factory()->for($this->list)->create(['title' => 'Alt']);
        $this->patch(route('tasks.update', $task), ['title' => 'Neu']);

        $this->undo()->assertSessionHas('toast.message', 'Rückgängig gemacht: Aufgabe umbenannt');
        $this->assertSame('Alt', $task->fresh()->title);

        $this->redo();
        $this->assertSame('Neu', $task->fresh()->title);
    }

    public function test_steps_are_undone_in_reverse_order()
    {
        $task = Task::factory()->for($this->list)->create(['title' => 'A']);
        $this->patch(route('tasks.update', $task), ['title' => 'B']);
        $this->patch(route('tasks.update', $task), ['title' => 'C']);

        $this->undo();
        $this->assertSame('B', $task->fresh()->title);
        $this->undo();
        $this->assertSame('A', $task->fresh()->title);
        $this->redo();
        $this->assertSame('B', $task->fresh()->title);
    }

    public function test_a_new_change_clears_what_could_be_redone()
    {
        $task = Task::factory()->for($this->list)->create(['title' => 'A']);
        $this->patch(route('tasks.update', $task), ['title' => 'B']);
        $this->undo();
        $this->patch(route('tasks.update', $task), ['title' => 'C']);

        $this->redo();

        $this->assertSame('C', $task->fresh()->title);
    }

    public function test_deleted_tasks_come_back_with_their_comments()
    {
        $task = Task::factory()->for($this->list)->create();
        Comment::factory()->for($task)->create();
        $task->assignees()->attach($this->owner);

        $this->delete(route('tasks.destroy', $task))->assertSessionHas('toast.undo', $this->list->id);
        $this->assertSoftDeleted($task);

        $this->undo();
        $this->assertNotSoftDeleted($task);
        $this->assertSame(1, $task->comments()->count());
        $this->assertSame([$this->owner->id], $task->assignees()->pluck('users.id')->all());
    }

    public function test_creating_a_task_can_be_undone()
    {
        $this->post(route('lists.tasks.store', $this->list), ['title' => 'Neu']);

        $this->undo();
        $this->assertSame(0, $this->list->tasks()->count());

        $this->redo();
        $this->assertSame(['Neu'], $this->list->tasks()->pluck('title')->all());
    }

    public function test_undoing_a_move_also_restores_the_dependents_it_pushed_back()
    {
        $first = Task::factory()->for($this->list)->create(['start_date' => '2026-11-10', 'due_date' => '2026-11-12']);
        $second = Task::factory()->for($this->list)->create(['start_date' => '2026-11-13', 'due_date' => '2026-11-14']);
        $second->dependencies()->attach($first);

        $this->patch(route('tasks.update', $first), ['start_date' => '2026-11-15', 'due_date' => '2026-11-17']);
        $this->assertSame('2026-11-18', $second->fresh()->start_date->toDateString());

        $this->undo();
        $this->assertSame('2026-11-10', $first->fresh()->start_date->toDateString());
        $this->assertSame('2026-11-13', $second->fresh()->start_date->toDateString());
    }

    public function test_status_changes_restore_the_completion_date()
    {
        $task = Task::factory()->for($this->list)->create();
        $done = $this->list->statuses()->where('is_done', true)->value('id');

        $this->patch(route('tasks.update', $task), ['status_id' => $done]);
        $this->assertNotNull($task->fresh()->completed_at);

        $this->undo();
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_steps_are_kept_per_person()
    {
        $task = Task::factory()->for($this->list)->create(['title' => 'A']);
        $this->patch(route('tasks.update', $task), ['title' => 'B']);
        $client = User::factory()->client()->create();
        Membership::create(['user_id' => $client->id, 'task_list_id' => $this->list->id, 'level' => AccessLevel::Edit]);

        $this->actingAs($client)->post(route('lists.undo', $this->list));

        $this->assertSame('B', $task->fresh()->title);
    }

    public function test_undo_refuses_when_the_task_changed_since()
    {
        $task = Task::factory()->for($this->list)->create(['title' => 'A']);
        $this->patch(route('tasks.update', $task), ['title' => 'B']);
        $task->update(['title' => 'Von jemand anderem']);

        $this->undo()->assertSessionHasErrors('history');

        $this->assertSame('Von jemand anderem', $task->fresh()->title);
    }

    public function test_the_list_page_names_the_next_undo_and_redo()
    {
        $task = Task::factory()->for($this->list)->create();
        $this->patch(route('tasks.update', $task), ['due_date' => '2026-12-01']);

        $this->get(route('lists.show', $this->list))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('history', ['undo' => 'Zeitraum geändert', 'redo' => null]));
    }

    public function test_viewers_cannot_undo()
    {
        $client = User::factory()->client()->create();
        Membership::create(['user_id' => $client->id, 'task_list_id' => $this->list->id, 'level' => AccessLevel::View]);

        $this->actingAs($client)->post(route('lists.undo', $this->list))->assertForbidden();
    }
}
