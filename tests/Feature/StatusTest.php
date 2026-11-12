<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The list's current statuses in the format the settings dialog submits.
     *
     * @return list<array<string, mixed>>
     */
    private function submitted(TaskList $list): array
    {
        return $list->statuses->map->only('id', 'name', 'color', 'is_done')->all();
    }

    public function test_owner_can_add_and_reorder_statuses()
    {
        $list = TaskList::factory()->create();
        $statuses = $this->submitted($list);

        $this->actingAs(User::factory()->create())->put(route('lists.statuses.sync', $list), ['statuses' => [
            ['id' => null, 'name' => 'Idee', 'color' => '#a855f7', 'is_done' => false],
            ...array_reverse($statuses),
        ]]);

        $this->assertSame(
            ['Idee', 'Erledigt', 'In Review', 'In Bearbeitung', 'Offen'],
            $list->statuses()->pluck('name')->all(),
        );
    }

    public function test_tasks_of_a_removed_status_move_to_a_similar_status()
    {
        $list = TaskList::factory()->create();
        [$open, $inProgress] = $list->statuses;
        $task = Task::factory()->for($list)->create(['status_id' => $inProgress->id]);
        $remaining = array_values(array_filter($this->submitted($list), fn (array $status) => $status['id'] !== $inProgress->id));

        $this->actingAs(User::factory()->create())->put(route('lists.statuses.sync', $list), ['statuses' => $remaining]);

        $this->assertSame($open->id, $task->fresh()->status_id);
    }

    public function test_switching_a_status_to_done_completes_its_tasks()
    {
        $list = TaskList::factory()->create();
        $task = Task::factory()->for($list)->create(['status_id' => $list->statuses[2]->id]);
        $statuses = $this->submitted($list);
        $statuses[2]['is_done'] = true;

        $this->actingAs(User::factory()->create())->put(route('lists.statuses.sync', $list), ['statuses' => $statuses]);

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_a_list_needs_an_open_and_a_done_status()
    {
        $list = TaskList::factory()->create();
        $openOnly = array_values(array_filter($this->submitted($list), fn (array $status) => ! $status['is_done']));

        $this->actingAs(User::factory()->create())
            ->put(route('lists.statuses.sync', $list), ['statuses' => $openOnly])
            ->assertSessionHasErrors('statuses');
    }
}
