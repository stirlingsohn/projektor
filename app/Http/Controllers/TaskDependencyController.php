<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\TaskHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TaskDependencyController extends Controller
{
    /**
     * Make the task wait for another task of the same list, pushing it back
     * if it would otherwise start too early.
     */
    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('editTasks', $task->taskList);

        $dependsOnId = $request->validate([
            'depends_on_id' => [
                'required',
                'integer',
                Rule::notIn([$task->id]),
                Rule::exists('tasks', 'id')->where('task_list_id', $task->task_list_id)->whereNull('deleted_at'),
            ],
        ])['depends_on_id'];

        $dependsOn = Task::findOrFail($dependsOnId);
        if ($dependsOn->dependsOn($task)) {
            throw ValidationException::withMessages([
                'depends_on_id' => __('This would create a circular dependency.'),
            ]);
        }

        TaskHistory::record($request->user(), $task->taskList, __('Dependency added'), function () use ($task, $dependsOn) {
            $task->dependencies()->syncWithoutDetaching([$dependsOn->id]);
            $dependsOn->pushDependents();
        });

        return back();
    }

    public function destroy(Request $request, Task $task, Task $dependsOn): RedirectResponse
    {
        Gate::authorize('editTasks', $task->taskList);

        TaskHistory::record($request->user(), $task->taskList, __('Dependency removed'), fn () => $task->dependencies()->detach($dependsOn->id));

        return back();
    }
}
