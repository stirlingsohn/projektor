<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_comments_are_shown_with_their_task()
    {
        $task = Task::factory()->create();
        Comment::factory()->for($task)->create(['body' => 'Erster']);
        Comment::factory()->for($task)->create(['body' => 'Zweiter']);

        $this->actingAs(User::factory()->create())->get(route('lists.show', $task->taskList))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tasks.0.comments_count', 2)
            ->where('comments.0.body', 'Erster'));
    }

    public function test_people_can_edit_only_their_own_comments()
    {
        $comment = Comment::factory()->create();

        $this->actingAs(User::factory()->create())->patch(route('comments.update', $comment), ['body' => 'Geändert'])->assertForbidden();
        $this->actingAs($comment->user)->patch(route('comments.update', $comment), ['body' => 'Geändert'])->assertSessionHasNoErrors();

        $this->assertSame('Geändert', $comment->fresh()->body);
    }

    public function test_the_owner_can_delete_any_comment()
    {
        $comment = Comment::factory()->create(['user_id' => User::factory()->client()]);

        $this->actingAs(User::factory()->create())->delete(route('comments.destroy', $comment));

        $this->assertModelMissing($comment);
    }

    public function test_empty_comments_are_rejected()
    {
        $task = Task::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('tasks.comments.store', $task), ['body' => ''])->assertSessionHasErrors('body');
    }
}
