<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('comment', $task->taskList);

        $task->comments()->create([
            'user_id' => $request->user()->id,
            ...$request->validate(['body' => ['required', 'string', 'max:5000']]),
        ]);

        return back();
    }

    public function update(Request $request, Comment $comment): RedirectResponse
    {
        Gate::authorize('update', $comment);

        $comment->update($request->validate(['body' => ['required', 'string', 'max:5000']]));

        return back();
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return back();
    }
}
