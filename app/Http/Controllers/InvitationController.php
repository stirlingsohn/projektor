<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Project;
use App\Models\User;
use App\Notifications\InvitedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    /** The page behind the link in the invitation email. */
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $invitation = Invitation::findPending($token);
        if (! $invitation) {
            return Inertia::render('invitations/Invalid');
        }

        $user = $request->user();
        if ($user && mb_strtolower($user->email) !== $invitation->email) {
            return Inertia::render('invitations/Invalid', ['otherAccount' => $user->email]);
        }

        // An account may have been created for this address since the invitation was sent.
        $existing = $user ?? User::whereRaw('lower(email) = ?', [$invitation->email])->first();
        if ($existing) {
            $this->accept($invitation, $existing);

            if ($user) {
                return redirect($this->targetUrl($invitation));
            }
            $request->session()->put('url.intended', $this->targetUrl($invitation));

            return to_route('login');
        }

        $target = $invitation->target();

        return Inertia::render('invitations/Accept', [
            'token' => $token,
            'email' => $invitation->email,
            'inviter' => $invitation->inviter?->name,
            'target' => $target->name,
            'level' => $invitation->level->label(),
        ]);
    }

    /** Create the account for an invitation and sign in. */
    public function store(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::findPending($token);
        abort_unless($invitation, 410, __('This invitation is no longer valid.'));
        abort_if(User::whereRaw('lower(email) = ?', [$invitation->email])->exists(), 409);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($invitation, $validated) {
            $user = new User([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => Hash::make($validated['password']),
            ]);
            $user->role = UserRole::Client;
            // The link in the email proves the address.
            $user->email_verified_at = now();
            $user->save();
            $this->accept($invitation, $user);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect($this->targetUrl($invitation));
    }

    public function resend(Request $request, Invitation $invitation): RedirectResponse
    {
        Gate::authorize('update', $invitation->target());

        $invitation->update(['invited_by' => $request->user()->id]);
        Notification::route('mail', $invitation->email)->notify(new InvitedNotification($invitation, $invitation->renewToken()));

        return back();
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        Gate::authorize('update', $invitation->target());

        $invitation->delete();

        return back();
    }

    private function accept(Invitation $invitation, User $user): void
    {
        $column = $invitation->project_id ? 'project_id' : 'task_list_id';
        $membership = Membership::firstOrNew(['user_id' => $user->id, $column => $invitation->{$column}]);
        // Never lower access someone already has.
        if (! $membership->exists || $invitation->level->rank() > $membership->level->rank()) {
            $membership->fill(['level' => $invitation->level])->save();
        }
        $invitation->update(['accepted_at' => now()]);
        $user->refreshAccess();
    }

    private function targetUrl(Invitation $invitation): string
    {
        $target = $invitation->target();

        return $target instanceof Project ? route('projects.show', $target) : route('lists.show', $target);
    }
}
