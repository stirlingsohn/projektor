<?php

namespace App\Http\Controllers;

use App\Models\TaskList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StatusController extends Controller
{
    /**
     * Replace a list's statuses with the submitted ones, in order. Tasks in
     * a removed status move to the first remaining status that is equally
     * done or not done.
     */
    public function sync(Request $request, TaskList $list): RedirectResponse
    {
        Gate::authorize('update', $list);

        $submitted = collect($request->validate([
            'statuses' => ['required', 'array', 'max:20'],
            'statuses.*.id' => ['nullable', 'integer', Rule::exists('statuses', 'id')->where('task_list_id', $list->id)],
            'statuses.*.name' => ['required', 'string', 'max:50'],
            'statuses.*.color' => ['required', 'hex_color'],
            'statuses.*.is_done' => ['required', 'boolean'],
        ])['statuses']);

        if ($submitted->where('is_done', true)->isEmpty() || $submitted->where('is_done', false)->isEmpty()) {
            throw ValidationException::withMessages([
                'statuses' => __('A list needs at least one open and one done status.'),
            ]);
        }

        DB::transaction(function () use ($list, $submitted) {
            $kept = $submitted->values()->map(fn (array $attributes, int $position) => $list->statuses()->updateOrCreate(
                ['id' => $attributes['id'] ?? null],
                [...collect($attributes)->only('name', 'color', 'is_done')->all(), 'position' => $position],
            ));

            foreach ($list->statuses()->whereNotIn('id', $kept->pluck('id'))->get() as $removed) {
                $replacement = $kept->firstWhere('is_done', $removed->is_done);
                $list->tasks()->where('status_id', $removed->id)->update(['status_id' => $replacement->id]);
                $removed->delete();
            }

            // A status may have been switched between open and done.
            $doneIds = $kept->where('is_done', true)->pluck('id');
            $list->tasks()->whereIn('status_id', $doneIds)->whereNull('completed_at')->update(['completed_at' => now()]);
            $list->tasks()->whereNotIn('status_id', $doneIds)->whereNotNull('completed_at')->update(['completed_at' => null]);
        });

        return back();
    }
}
