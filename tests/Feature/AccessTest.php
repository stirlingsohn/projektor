<?php

namespace Tests\Feature;

use App\Enums\AccessLevel;
use App\Models\Attachment;
use App\Models\Document;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * What clients may see and do with each access level.
 */
class AccessTest extends TestCase
{
    use RefreshDatabase;

    private function clientWith(AccessLevel $level, Project|TaskList $target): User
    {
        $client = User::factory()->client()->create();
        Membership::create([
            'user_id' => $client->id,
            $target instanceof Project ? 'project_id' : 'task_list_id' => $target->id,
            'level' => $level,
        ]);

        return $client;
    }

    public function test_project_access_includes_subprojects_and_their_lists()
    {
        $project = Project::factory()->create();
        $subproject = Project::factory()->create(['parent_id' => $project->id]);
        $list = TaskList::factory()->for($subproject)->create();
        Project::factory()->create(['name' => 'Fremd']);
        $client = $this->clientWith(AccessLevel::View, $project);

        $this->actingAs($client)->get(route('lists.show', $list))->assertOk();
        $this->actingAs($client)->get(route('dashboard'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('projects', fn ($projects) => collect($projects)->pluck('id')->sort()->values()->all() === [$project->id, $subproject->id]));
    }

    public function test_a_shared_list_shows_its_project_but_nothing_else_in_it()
    {
        $list = TaskList::factory()->create();
        $otherList = TaskList::factory()->for($list->project)->create();
        Document::factory()->for($list->project)->create();
        Attachment::factory()->for($list->project)->create();
        $client = $this->clientWith(AccessLevel::View, $list);

        $this->actingAs($client)->get(route('projects.show', $list->project))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('attachments', 0)
            ->where('can.viewContents', false)
            ->has('lists', 1)
            ->where('lists.0.id', $list->id)
            ->has('documents', 0));
        $this->actingAs($client)->get(route('lists.show', $otherList))->assertForbidden();
    }

    public function test_viewers_cannot_change_tasks_or_comment()
    {
        $task = Task::factory()->create();
        $client = $this->clientWith(AccessLevel::View, $task->taskList);

        $this->actingAs($client)->patch(route('tasks.update', $task), ['title' => 'Neu'])->assertForbidden();
        $this->actingAs($client)->post(route('tasks.comments.store', $task), ['body' => 'Hallo'])->assertForbidden();
        $this->actingAs($client)->post(route('timer.start', $task))->assertForbidden();
    }

    public function test_commenters_can_comment_but_not_change_tasks()
    {
        $task = Task::factory()->create();
        $client = $this->clientWith(AccessLevel::Comment, $task->taskList->project);

        $this->actingAs($client)->post(route('tasks.comments.store', $task), ['body' => 'Sieht gut aus'])->assertSessionHasNoErrors();
        $this->actingAs($client)->patch(route('tasks.update', $task), ['title' => 'Neu'])->assertForbidden();

        $this->assertSame('Sieht gut aus', $task->comments()->sole()->body);
    }

    public function test_editors_can_work_on_tasks_but_not_manage_the_list()
    {
        $task = Task::factory()->create();
        $client = $this->clientWith(AccessLevel::Edit, $task->taskList);

        $this->actingAs($client)->patch(route('tasks.update', $task), ['title' => 'Neu'])->assertSessionHasNoErrors();
        $this->actingAs($client)->post(route('lists.tasks.store', $task->taskList), ['title' => 'Zweite'])->assertSessionHasNoErrors();
        $this->actingAs($client)->post(route('timer.start', $task))->assertSessionHasNoErrors();
        $this->actingAs($client)->patch(route('lists.update', $task->taskList), ['name' => 'Umbenannt'])->assertForbidden();
        $this->actingAs($client)->delete(route('lists.destroy', $task->taskList))->assertForbidden();

        $this->assertSame('Neu', $task->fresh()->title);
    }

    public function test_editors_of_a_project_can_write_documents_but_viewers_only_read_them()
    {
        $document = Document::factory()->create();
        $editor = $this->clientWith(AccessLevel::Edit, $document->project);
        $viewer = $this->clientWith(AccessLevel::View, $document->project);

        $this->actingAs($viewer)->get(route('documents.show', $document))->assertInertia(fn (AssertableInertia $page) => $page->where('canEdit', false));
        $this->actingAs($viewer)->put(route('documents.update', $document), ['title' => 'Nein'])->assertForbidden();
        $this->actingAs($editor)->put(route('documents.update', $document), ['title' => 'Ja'])->assertSessionHasNoErrors();
        $this->actingAs($editor)->delete(route('documents.destroy', $document))->assertForbidden();
    }

    public function test_the_highest_level_wins_when_access_comes_from_several_places()
    {
        $project = Project::factory()->create();
        $subproject = Project::factory()->create(['parent_id' => $project->id]);
        $task = Task::factory()->for(TaskList::factory()->for($subproject))->create();
        $client = $this->clientWith(AccessLevel::View, $project);
        Membership::create(['user_id' => $client->id, 'project_id' => $subproject->id, 'level' => AccessLevel::Edit]);

        $this->actingAs($client)->patch(route('tasks.update', $task), ['title' => 'Neu'])->assertSessionHasNoErrors();
    }

    public function test_breadcrumbs_leave_out_projects_the_client_cannot_see()
    {
        $project = Project::factory()->create();
        $subproject = Project::factory()->create(['parent_id' => $project->id]);
        $client = $this->clientWith(AccessLevel::View, $subproject);

        $this->actingAs($client)->get(route('projects.show', $subproject))->assertInertia(fn (AssertableInertia $page) => $page->has('ancestors', 0));
        $this->actingAs($client)->get(route('projects.show', $project))->assertForbidden();
    }

    public function test_clients_cannot_share_projects()
    {
        $project = Project::factory()->create();
        $client = $this->clientWith(AccessLevel::Edit, $project);

        $this->actingAs($client)
            ->post(route('projects.members.store', $project), ['email' => 'freund@example.com', 'level' => 'edit'])
            ->assertForbidden();
    }
}
