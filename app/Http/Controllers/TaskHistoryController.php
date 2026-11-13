<?php

namespace App\Http\Controllers;

use App\Models\TaskList;
use App\Support\TaskHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskHistoryController extends Controller
{
    public function undo(Request $request, TaskList $list): RedirectResponse
    {
        Gate::authorize('editTasks', $list);

        $step = TaskHistory::undo($request->user(), $list);

        return back()->with('toast', $step ? ['message' => __('Undone: :change', ['change' => $step->label]), 'redo' => $list->id] : null);
    }

    public function redo(Request $request, TaskList $list): RedirectResponse
    {
        Gate::authorize('editTasks', $list);

        $step = TaskHistory::redo($request->user(), $list);

        return back()->with('toast', $step ? ['message' => __('Redone: :change', ['change' => $step->label]), 'undo' => $list->id] : null);
    }
}
