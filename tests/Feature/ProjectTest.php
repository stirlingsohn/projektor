<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_projects()
    {
        $project = Project::factory()->create();

        $this->get(route('projects.show', $project))->assertRedirect('/login');
    }

    public function test_owner_sees_projects_in_the_sidebar()
    {
        Project::factory()->create(['name' => 'Album']);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('projects.0.name', 'Album'));
    }

    public function test_owner_can_view_a_project_with_ancestors_and_children()
    {
        $album = Project::factory()->create(['name' => 'Album']);
        $single = Project::factory()->for($album, 'parent')->create(['name' => 'Single']);
        Project::factory()->for($single, 'parent')->create(['name' => 'Remix']);

        $this->actingAs(User::factory()->create())
            ->get(route('projects.show', $single))
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/Show')
                ->where('ancestors.0.name', 'Album')
                ->where('children.0.name', 'Remix'));
    }

    public function test_owner_can_create_a_subproject()
    {
        $parent = Project::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('projects.store'), ['name' => 'Mixing', 'color' => '#22c55e', 'parent_id' => $parent->id]);

        $this->assertDatabaseHas('projects', ['name' => 'Mixing', 'parent_id' => $parent->id]);
    }

    public function test_new_projects_are_appended_to_their_siblings()
    {
        Project::factory()->create(['position' => 4]);

        $this->actingAs(User::factory()->create())->post(route('projects.store'), ['name' => 'Neu']);

        $this->assertSame(5, Project::firstWhere('name', 'Neu')->position);
    }

    public function test_owner_can_rename_a_project()
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('projects.update', $project), ['name' => 'Umbenannt']);

        $this->assertSame('Umbenannt', $project->fresh()->name);
    }

    public function test_a_project_cannot_be_moved_into_its_own_subproject()
    {
        $parent = Project::factory()->create();
        $grandchild = Project::factory()->for(Project::factory()->for($parent, 'parent'), 'parent')->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('projects.update', $parent), ['name' => $parent->name, 'parent_id' => $grandchild->id])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_deleting_a_project_deletes_its_subprojects()
    {
        $parent = Project::factory()->create();
        Project::factory()->for($parent, 'parent')->create();

        $this->actingAs(User::factory()->create())->delete(route('projects.destroy', $parent));

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_deleting_the_viewed_project_redirects_to_its_parent()
    {
        $parent = Project::factory()->create();
        $child = Project::factory()->for($parent, 'parent')->create();

        $this->actingAs(User::factory()->create())
            ->from(route('projects.show', $child))
            ->delete(route('projects.destroy', $child))
            ->assertRedirect(route('projects.show', $parent));
    }

    public function test_deleting_another_project_stays_on_the_current_page()
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->create())
            ->from('/dashboard')
            ->delete(route('projects.destroy', $project))
            ->assertRedirect('/dashboard');
    }

    public function test_clients_cannot_see_projects_they_were_not_invited_to()
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->client()->create())
            ->get(route('projects.show', $project))
            ->assertForbidden();
    }

    public function test_clients_cannot_create_projects()
    {
        $this->actingAs(User::factory()->client()->create())
            ->post(route('projects.store'), ['name' => 'Fremd'])
            ->assertForbidden();
    }
}
