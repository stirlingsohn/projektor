<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TimeEntryController extends Controller
{
    /** Start the timer on a task, stopping any timer that is still running. */
    public function start(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task->taskList);

        DB::transaction(function () use ($request, $task) {
            $request->user()->timeEntries()->running()->lockForUpdate()->get()->each->stop();
            $request->user()->timeEntries()->create(['task_id' => $task->id, 'started_at' => now()]);
        });

        return back();
    }

    public function stop(Request $request): RedirectResponse
    {
        $request->user()->timeEntries()->running()->get()->each->stop();

        return back();
    }

    /** Add time by hand. */
    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task->taskList);

        $request->user()->timeEntries()->create([
            'task_id' => $task->id,
            ...$this->attributes($request->validate($this->rules())),
        ]);

        return back();
    }

    public function update(Request $request, TimeEntry $timeEntry): RedirectResponse
    {
        Gate::authorize('update', $timeEntry);
        abort_if($timeEntry->ended_at === null, 409, __('Stop the timer before changing this entry.'));

        $timeEntry->update($this->attributes($request->validate($this->rules())));

        return back();
    }

    public function destroy(TimeEntry $timeEntry): RedirectResponse
    {
        Gate::authorize('delete', $timeEntry);

        $timeEntry->delete();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Manual entries start at the beginning of their day.
     *
     * @param  array{date: string, minutes: int, note?: string|null}  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        $startedAt = Carbon::parse($validated['date'])->startOfDay();
        $seconds = $validated['minutes'] * 60;

        return [
            'started_at' => $startedAt,
            'ended_at' => $startedAt->copy()->addSeconds($seconds),
            'seconds' => $seconds,
            'note' => $validated['note'] ?? null,
        ];
    }
}
