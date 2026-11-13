<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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

        $list->tasks()->create([
            ...$attributes,
            'status_id' => $attributes['status_id'] ?? $list->statuses()->where('is_done', false)->value('id'),
            'position' => $list->tasks()->max('position') + 1,
        ]);

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

        DB::transaction(function () use ($task, $validated) {
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

    public function destroy(Task $task): RedirectResponse
    {
        Gate::authorize('editTasks', $task->taskList);

        $task->delete();

        return back();
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

        $sibling = Rule::exists('tasks', 'id')->where('task_list_id', $task->task_list_id);
        $attributes = $request->validate([
            'status_id' => ['sometimes', 'required', Rule::exists('statuses', 'id')->where('task_list_id', $task->task_list_id)],
            'after_id' => ['nullable', 'integer', Rule::notIn([$task->id]), $sibling],
            'before_id' => ['nullable', 'integer', Rule::notIn([$task->id]), $sibling],
        ]);

        DB::transaction(function () use ($task, $attributes) {
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
}
