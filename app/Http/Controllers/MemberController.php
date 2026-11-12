<?php

namespace App\Http\Controllers;

use App\Enums\AccessLevel;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Project;
use App\Models\TaskList;
use App\Models\User;
use App\Notifications\AccessGrantedNotification;
use App\Notifications\InvitedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Sharing projects and lists: people with an account get access right
 * away, everyone else gets an invitation by email.
 */
class MemberController extends Controller
{
    public function storeForProject(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        return $this->share($request, $project, 'project_id');
    }

    public function storeForList(Request $request, TaskList $list): RedirectResponse
    {
        Gate::authorize('update', $list);

        return $this->share($request, $list, 'task_list_id');
    }

    public function update(Request $request, Membership $membership): RedirectResponse
    {
        Gate::authorize('update', $membership->project ?? $membership->taskList);

        $membership->update($request->validate(['level' => ['required', Rule::enum(AccessLevel::class)]]));

        return back();
    }

    public function destroy(Membership $membership): RedirectResponse
    {
        Gate::authorize('update', $membership->project ?? $membership->taskList);

        $membership->delete();

        return back();
    }

    private function share(Request $request, Project|TaskList $target, string $column): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'level' => ['required', Rule::enum(AccessLevel::class)],
        ]);
        $email = mb_strtolower($validated['email']);
        $level = AccessLevel::from($validated['level']);
        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        if ($user?->isOwner()) {
            throw ValidationException::withMessages(['email' => __('You already have access to everything.')]);
        }

        if ($user) {
            Membership::updateOrCreate(['user_id' => $user->id, $column => $target->id], ['level' => $level]);
            $user->notify(new AccessGrantedNotification($target, $level, $request->user()));

            return back();
        }

        $invitation = Invitation::whereNull('accepted_at')->firstOrNew(['email' => $email, $column => $target->id]);
        $invitation->fill(['level' => $level, 'invited_by' => $request->user()->id]);
        $token = $invitation->renewToken();
        Notification::route('mail', $email)->notify(new InvitedNotification($invitation, $token));

        return back();
    }
}
