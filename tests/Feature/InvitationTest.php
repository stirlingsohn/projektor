<?php

namespace Tests\Feature;

use App\Enums\AccessLevel;
use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Project;
use App\Models\TaskList;
use App\Models\User;
use App\Notifications\AccessGrantedNotification;
use App\Notifications\InvitedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    /** Invite by email and return the token from the email. */
    private function invite(Project|TaskList $target, string $email = 'kunde@example.com', string $level = 'comment'): string
    {
        Notification::fake();
        $route = $target instanceof Project ? route('projects.members.store', $target) : route('lists.members.store', $target);
        $this->actingAs(User::factory()->create())->post($route, ['email' => $email, 'level' => $level])->assertSessionHasNoErrors();

        $token = null;
        Notification::assertSentTo(new AnonymousNotifiable, InvitedNotification::class, function (InvitedNotification $notification, array $channels, AnonymousNotifiable $notifiable) use (&$token, $email) {
            $token = basename(parse_url($notification->toMail($notifiable)->actionUrl, PHP_URL_PATH));

            return $notifiable->routes['mail'] === $email;
        });
        auth()->logout();

        return $token;
    }

    public function test_inviting_a_new_address_sends_an_invitation()
    {
        $project = Project::factory()->create();

        $token = $this->invite($project);

        $invitation = Invitation::sole();
        $this->assertSame('kunde@example.com', $invitation->email);
        $this->assertSame(AccessLevel::Comment, $invitation->level);
        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame($invitation->id, Invitation::findPending($token)->id);
    }

    public function test_accepting_creates_a_client_account_with_access()
    {
        $project = Project::factory()->create();
        $token = $this->invite($project);

        $this->get(route('invitations.show', $token))->assertInertia(fn ($page) => $page
            ->component('invitations/Accept')
            ->where('email', 'kunde@example.com')
            ->where('target', $project->name));

        $this->post(route('invitations.store', $token), ['name' => 'Kim Kunde', 'password' => 'geheim-1234', 'password_confirmation' => 'geheim-1234'])
            ->assertRedirect(route('projects.show', $project));

        $client = User::firstWhere('email', 'kunde@example.com');
        $this->assertAuthenticatedAs($client);
        $this->assertSame(UserRole::Client, $client->role);
        $this->assertNotNull($client->email_verified_at);
        $this->assertSame(AccessLevel::Comment, $client->accessToProject($project));
        $this->assertNotNull(Invitation::sole()->accepted_at);
    }

    public function test_an_invitation_can_only_be_used_once()
    {
        $token = $this->invite(Project::factory()->create());
        $this->post(route('invitations.store', $token), ['name' => 'Kim', 'password' => 'geheim-1234', 'password_confirmation' => 'geheim-1234']);
        auth()->logout();

        $this->get(route('invitations.show', $token))->assertInertia(fn ($page) => $page->component('invitations/Invalid'));
    }

    public function test_expired_invitations_are_rejected()
    {
        $token = $this->invite(Project::factory()->create());
        $this->travel(Invitation::VALID_DAYS + 1)->days();

        $this->get(route('invitations.show', $token))->assertInertia(fn ($page) => $page->component('invitations/Invalid'));
        $this->post(route('invitations.store', $token), ['name' => 'Kim', 'password' => 'geheim-1234', 'password_confirmation' => 'geheim-1234'])->assertGone();
    }

    public function test_people_with_an_account_get_access_right_away()
    {
        Notification::fake();
        $client = User::factory()->client()->create(['email' => 'kunde@example.com']);
        $list = TaskList::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('lists.members.store', $list), ['email' => 'Kunde@Example.com', 'level' => 'edit']);

        $this->assertSame(AccessLevel::Edit, $client->accessToList($list));
        $this->assertSame(0, Invitation::count());
        Notification::assertSentTo($client, AccessGrantedNotification::class);
    }

    public function test_the_owner_cannot_invite_themselves()
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('projects.members.store', Project::factory()->create()), ['email' => $owner->email, 'level' => 'view'])
            ->assertSessionHasErrors('email');
    }

    public function test_inviting_again_replaces_the_earlier_link()
    {
        $project = Project::factory()->create();
        $first = $this->invite($project);
        $second = $this->invite($project, level: 'edit');

        $this->assertSame(1, Invitation::count());
        $this->assertNull(Invitation::findPending($first));
        $this->assertSame(AccessLevel::Edit, Invitation::findPending($second)->level);
    }

    public function test_the_owner_can_change_and_remove_access()
    {
        $membership = Membership::factory()->create();
        $owner = User::factory()->create();

        $this->actingAs($owner)->patch(route('memberships.update', $membership), ['level' => 'edit']);
        $this->assertSame(AccessLevel::Edit, $membership->fresh()->level);

        $this->actingAs($owner)->delete(route('memberships.destroy', $membership));
        $this->assertModelMissing($membership);
    }

    public function test_revoked_invitations_no_longer_work()
    {
        $token = $this->invite(Project::factory()->create());

        $this->actingAs(User::factory()->create())->delete(route('invitations.destroy', Invitation::sole()));
        auth()->logout();

        $this->get(route('invitations.show', $token))->assertInertia(fn ($page) => $page->component('invitations/Invalid'));
    }
}
