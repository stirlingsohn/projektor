<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Attachment::DISK);
    }

    public function test_files_can_be_uploaded_to_a_project()
    {
        $project = Project::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('projects.attachments.store', $project), [
            'files' => [UploadedFile::fake()->image('Cover.png'), UploadedFile::fake()->create('Demo.mp3', 200, 'audio/mpeg')],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Demo.mp3', 'Cover.png'], $project->attachments()->pluck('name')->sort()->reverse()->values()->all());
        Storage::disk(Attachment::DISK)->assertExists($project->attachments()->pluck('path')->all());
    }

    public function test_files_can_be_attached_to_a_task_of_the_project()
    {
        $task = Task::factory()->create();
        $project = $task->taskList->project;

        $this->actingAs(User::factory()->create())->post(route('projects.attachments.store', $project), [
            'files' => [UploadedFile::fake()->create('Mix v2.wav', 10)],
            'task_id' => $task->id,
        ]);

        $this->assertSame($task->id, Attachment::sole()->task_id);
    }

    public function test_tasks_of_other_projects_are_rejected()
    {
        $project = Project::factory()->create();
        $foreignTask = Task::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('projects.attachments.store', $project), [
            'files' => [UploadedFile::fake()->create('Mix.wav', 10)],
            'task_id' => $foreignTask->id,
        ])->assertSessionHasErrors('task_id');
    }

    public function test_images_are_shown_inline_and_html_is_only_downloaded()
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('projects.attachments.store', $project), [
            'files' => [UploadedFile::fake()->image('Cover.png'), UploadedFile::fake()->createWithContent('page.html', '<script>alert(1)</script>')],
        ]);
        [$image, $html] = [Attachment::firstWhere('name', 'Cover.png'), Attachment::firstWhere('name', 'page.html')];

        $this->get(route('attachments.show', $image))->assertOk()->assertHeader('Content-Disposition', 'inline; filename=Cover.png');
        $this->get(route('attachments.show', $html))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=page.html');
    }

    public function test_clients_cannot_see_files_of_other_projects()
    {
        $attachment = Attachment::factory()->create();

        $this->actingAs(User::factory()->client()->create())
            ->get(route('attachments.show', $attachment))
            ->assertForbidden();
    }

    public function test_deleting_a_file_removes_it_from_storage()
    {
        Storage::disk(Attachment::DISK)->put('attachments/1/notes.txt', 'Notizen');
        $attachment = Attachment::factory()->create(['path' => 'attachments/1/notes.txt']);

        $this->actingAs(User::factory()->create())->delete(route('attachments.destroy', $attachment));

        $this->assertModelMissing($attachment);
        Storage::disk(Attachment::DISK)->assertMissing('attachments/1/notes.txt');
    }

    public function test_deleting_a_project_removes_the_files_of_its_subprojects()
    {
        $project = Project::factory()->create();
        $subproject = Project::factory()->create(['parent_id' => $project->id]);
        Storage::disk(Attachment::DISK)->put('attachments/2/beat.wav', 'beat');
        Attachment::factory()->for($subproject)->create(['path' => 'attachments/2/beat.wav']);

        $this->actingAs(User::factory()->create())->delete(route('projects.destroy', $project));

        Storage::disk(Attachment::DISK)->assertMissing('attachments/2/beat.wav');
    }

    public function test_files_of_a_deleted_task_stay_in_the_project()
    {
        $task = Task::factory()->create();
        $attachment = Attachment::factory()->create(['project_id' => $task->taskList->project_id, 'task_id' => $task->id]);

        $task->delete();
        $this->travel(Task::KEEP_DELETED_DAYS + 1)->days();
        $this->artisan('model:prune', ['--model' => Task::class]);

        $this->assertNull($attachment->fresh()->task_id);
    }

    public function test_lists_include_the_files_of_their_tasks()
    {
        $list = TaskList::factory()->create();
        $task = Task::factory()->for($list)->create();
        Attachment::factory()->create(['project_id' => $list->project_id, 'task_id' => $task->id]);
        Attachment::factory()->create(['project_id' => $list->project_id]);

        $this->actingAs(User::factory()->create())
            ->get(route('lists.show', $list))
            ->assertInertia(fn ($page) => $page->count('attachments', 1));
    }
}
