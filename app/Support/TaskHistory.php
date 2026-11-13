<?php

namespace App\Support;

use App\Models\Task;
use App\Models\TaskChange;
use App\Models\TaskList;
use App\Models\User;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Undo and redo for changes to the tasks of a list.
 *
 * Every change is recorded as the state of the tasks it touched before and
 * after, found by comparing the whole list. That also covers side effects
 * such as dependent tasks pushed back in the Gantt chart. Undo puts the
 * "before" state back, redo the "after" state, but only while the tasks
 * still look the way the step left them.
 */
class TaskHistory
{
    /** How many steps each person can go back per list. */
    private const LIMIT = 50;

    /** Columns that make up a task's state; timestamps are left out. */
    private const COLUMNS = ['status_id', 'title', 'description', 'start_date', 'due_date', 'position', 'completed_at', 'deleted_at'];

    /**
     * Run a change and record it, unless nothing changed.
     *
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     */
    public static function record(User $user, TaskList $list, string $label, Closure $change): mixed
    {
        return DB::transaction(function () use ($user, $list, $label, $change) {
            $before = self::snapshot($list);
            $result = $change();
            $after = self::snapshot($list);

            $tasks = $before->keys()->merge($after->keys())->unique()
                ->filter(fn (int $id) => $before->get($id) !== $after->get($id))
                ->map(fn (int $id) => ['id' => $id, 'before' => $before->get($id), 'after' => $after->get($id)])
                ->values();

            if ($tasks->isNotEmpty()) {
                // A new step replaces whatever could have been redone.
                self::steps($user, $list)->whereNotNull('undone_at')->delete();
                TaskChange::create(['user_id' => $user->id, 'task_list_id' => $list->id, 'label' => $label, 'tasks' => $tasks->all()]);
                self::steps($user, $list)->whereKeyNot(self::steps($user, $list)->latest('id')->limit(self::LIMIT)->pluck('id'))->delete();
            }

            return $result;
        });
    }

    /** The step that undo would revert, if any. */
    public static function undoable(User $user, TaskList $list): ?TaskChange
    {
        return self::steps($user, $list)->whereNull('undone_at')->latest('id')->first();
    }

    /** The step that redo would repeat, if any. */
    public static function redoable(User $user, TaskList $list): ?TaskChange
    {
        return self::steps($user, $list)->whereNotNull('undone_at')->oldest('id')->first();
    }

    public static function undo(User $user, TaskList $list): ?TaskChange
    {
        return self::replay(self::undoable($user, $list), from: 'after', to: 'before', undoneAt: now());
    }

    public static function redo(User $user, TaskList $list): ?TaskChange
    {
        return self::replay(self::redoable($user, $list), from: 'before', to: 'after', undoneAt: null);
    }

    private static function replay(?TaskChange $step, string $from, string $to, mixed $undoneAt): ?TaskChange
    {
        if (! $step) {
            return null;
        }

        return DB::transaction(function () use ($step, $from, $to, $undoneAt) {
            $current = self::snapshot($step->taskList);
            foreach ($step->tasks as $task) {
                if (! self::matches($current->get($task['id']), $task[$from])) {
                    throw ValidationException::withMessages([
                        'history' => __('This can no longer be undone because the tasks have changed in the meantime.'),
                    ]);
                }
            }

            foreach ($step->tasks as $task) {
                self::restore($task['id'], $task[$to]);
            }
            $step->update(['undone_at' => $undoneAt]);

            return $step;
        });
    }

    /**
     * A task that did not exist (null) matches one that is deleted now.
     *
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>|null  $expected
     */
    private static function matches(?array $current, ?array $expected): bool
    {
        return $expected === null ? $current === null || $current['deleted_at'] !== null : $current === $expected;
    }

    /**
     * @param  array<string, mixed>|null  $state
     */
    private static function restore(int $id, ?array $state): void
    {
        $task = Task::withTrashed()->find($id);
        if (! $task) {
            return;
        }

        if ($state === null) {
            // The task was created in this step: remove it again, but keep it for redo.
            $task->forceFill(['deleted_at' => now()])->saveQuietly();

            return;
        }

        // Quietly, so that `completed_at` is taken as recorded.
        $task->forceFill(collect($state)->only(self::COLUMNS)->all())->saveQuietly();
        DB::table('task_user')->where('task_id', $id)->delete();
        DB::table('task_user')->insert(array_map(fn (int $userId) => ['task_id' => $id, 'user_id' => $userId], $state['assignee_ids']));
        DB::table('task_dependencies')->where('task_id', $id)->delete();
        DB::table('task_dependencies')->insert(array_map(fn (int $dependsOn) => ['task_id' => $id, 'depends_on_id' => $dependsOn], $state['depends_on']));
    }

    /**
     * The state of every task in the list, deleted ones included, by id.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private static function snapshot(TaskList $list): Collection
    {
        $tasks = Task::withTrashed()->where('task_list_id', $list->id)->orderBy('id')->get();
        $ids = $tasks->modelKeys();
        $assignees = DB::table('task_user')->whereIn('task_id', $ids)->orderBy('user_id')->get()->groupBy('task_id');
        $dependencies = DB::table('task_dependencies')->whereIn('task_id', $ids)->orderBy('depends_on_id')->get()->groupBy('task_id');

        return $tasks->mapWithKeys(fn (Task $task) => [$task->id => [
            ...collect(self::COLUMNS)->mapWithKeys(fn (string $column) => [$column => $task->getRawOriginal($column)])->all(),
            'assignee_ids' => $assignees->get($task->id, collect())->pluck('user_id')->map(fn ($id) => (int) $id)->all(),
            'depends_on' => $dependencies->get($task->id, collect())->pluck('depends_on_id')->map(fn ($id) => (int) $id)->all(),
        ]]);
    }

    private static function steps(User $user, TaskList $list)
    {
        return TaskChange::where('user_id', $user->id)->where('task_list_id', $list->id);
    }
}
