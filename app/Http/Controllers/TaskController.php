<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskList;
use App\Support\TaskHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function store(Request $request, TaskList $list): RedirectResponse
    {
        Gate::authorize('editTasks', $list);

        $attributes = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status_id' => ['nullable', Rule::exists('statuses', 'id')->where('task_list_id', $list->id)],
        ]);

        TaskHistory::record($request->user(), $list, __('Task created'), fn () => $list->tasks()->create([
            ...$attributes,
            'status_id' => $attributes['status_id'] ?? $list->statuses()->where('is_done', false)->value('id'),
            'position' => $list->tasks()->withTrashed()->max('position') + 1,
        ]));

        return back();
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('editTasks', $task->taskList);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'status_id' => ['sometimes', 'required', Rule::exists('statuses', 'id')->where('task_list_id', $task->task_list_id)],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'assignee_ids' => ['sometimes', 'array'],
            'assignee_ids.*' => ['integer', Rule::in($task->taskList->people()->modelKeys())],
        ]);
        $task->fill(Arr::except($validated, 'assignee_ids'));

        if ($task->start_date && $task->due_date?->lt($task->start_date)) {
            throw ValidationException::withMessages(['due_date' => __('The end date must not be before the start date.')]);
        }

        TaskHistory::record($request->user(), $task->taskList, $this->label($validated), function () use ($task, $validated) {
            $datesChanged = $task->isDirty(['start_date', 'due_date']);
            $task->save();
            if (array_key_exists('assignee_ids', $validated)) {
                $task->assignees()->sync($validated['assignee_ids']);
            }
            if ($datesChanged) {
                $task->pushDependents();
            }
        });

        return back();
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('editTasks', $task->taskList);

        TaskHistory::record($request->user(), $task->taskList, __('Task deleted'), fn () => $task->delete());
        // A timer must not keep running on a task nobody can see.
        $task->timeEntries()->running()->get()->each->stop();

        return back()->with('toast', ['message' => __('Task deleted'), 'undo' => $task->task_list_id]);
    }

    /**
     * Move a task after drag and drop: optionally into another status, placed
     * directly after `after_id` or, failing that, directly before `before_id`.
     * The relative order of all other tasks in the list stays the same, so the
     * list and Kanban views share one order.
     */
    public function move(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('editTasks', $task->taskList);

        $sibling = Rule::exists('tasks', 'id')->where('task_list_id', $task->task_list_id)->whereNull('deleted_at');
        $attributes = $request->validate([
            'status_id' => ['sometimes', 'required', Rule::exists('statuses', 'id')->where('task_list_id', $task->task_list_id)],
            'after_id' => ['nullable', 'integer', Rule::notIn([$task->id]), $sibling],
            'before_id' => ['nullable', 'integer', Rule::notIn([$task->id]), $sibling],
        ]);

        $label = isset($attributes['status_id']) && (int) $attributes['status_id'] !== $task->status_id ? __('Status changed') : __('Task moved');
        TaskHistory::record($request->user(), $task->taskList, $label, function () use ($task, $attributes) {
            $task->update(Arr::only($attributes, 'status_id'));

            $ids = $task->taskList->tasks()->whereKeyNot($task->id)->pluck('id');
            $index = match (true) {
                isset($attributes['after_id']) => $ids->search($attributes['after_id']) + 1,
                isset($attributes['before_id']) => $ids->search($attributes['before_id']),
                default => null,
            };
            if ($index === null) {
                return;
            }

            $ids->splice($index, 0, [$task->id]);
            foreach ($ids as $position => $id) {
                Task::whereKey($id)->update(['position' => $position]);
            }
        });

        return back();
    }

    /**
     * A name for an edit, shown next to the undo button.
     *
     * @param  array<string, mixed>  $validated
     */
    private function label(array $validated): string
    {
        return match (true) {
            array_key_exists('status_id', $validated) => __('Status changed'),
            array_key_exists('start_date', $validated), array_key_exists('due_date', $validated) => __('Dates changed'),
            array_key_exists('assignee_ids', $validated) => __('Assignees changed'),
            array_key_exists('description', $validated) => __('Description changed'),
            default => __('Task renamed'),
        };
    }
}
