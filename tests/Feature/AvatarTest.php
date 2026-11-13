<?php

namespace Tests\Feature;

use App\Enums\AccessLevel;
use App\Models\Comment;
use App\Models\Membership;
use App\Models\TaskList;
use App\Models\User;
use App\Support\Avatar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Avatar::DISK);
    }

    private function clientOn(TaskList $list): User
    {
        $client = User::factory()->client()->create();
        Membership::create(['user_id' => $client->id, 'task_list_id' => $list->id, 'level' => AccessLevel::Comment]);

        return $client;
    }

    public function test_a_picture_is_cropped_to_a_small_square()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('avatar.store'), ['avatar' => UploadedFile::fake()->image('ich.png', 1200, 800)])
            ->assertSessionHasNoErrors();

        $path = $user->fresh()->avatar_path;
        [$width, $height] = getimagesizefromstring(Storage::disk(Avatar::DISK)->get($path));
        $this->assertSame([256, 256], [$width, $height]);
        $this->get(route('profile.edit'))->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.avatar', $user->fresh()->avatar));
    }

    public function test_replacing_or_removing_the_picture_deletes_the_old_file()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('avatar.store'), ['avatar' => UploadedFile::fake()->image('a.png')]);
        $first = $user->fresh()->avatar_path;

        $this->actingAs($user)->post(route('avatar.store'), ['avatar' => UploadedFile::fake()->image('b.png')]);
        Storage::disk(Avatar::DISK)->assertMissing($first);

        $this->actingAs($user)->delete(route('avatar.destroy'));
        $this->assertNull($user->fresh()->avatar_path);
        $this->assertSame([], Storage::disk(Avatar::DISK)->allFiles('avatars'));
    }

    public function test_only_images_are_accepted()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('avatar.store'), ['avatar' => UploadedFile::fake()->createWithContent('bild.svg', '<svg><script>alert(1)</script></svg>')])
            ->assertSessionHasErrors('avatar');
    }

    public function test_people_working_on_the_same_list_see_each_others_pictures()
    {
        $list = TaskList::factory()->create();
        $alice = $this->clientOn($list);
        $bob = $this->clientOn($list);
        $stranger = $this->clientOn(TaskList::factory()->create());
        $this->actingAs($bob)->post(route('avatar.store'), ['avatar' => UploadedFile::fake()->image('bob.png')]);

        $this->actingAs($alice)->get(route('users.avatar', $bob))->assertOk();
        $this->actingAs($stranger)->get(route('users.avatar', $bob))->assertForbidden();
    }

    public function test_comments_carry_the_authors_picture()
    {
        $comment = Comment::factory()->create();
        $this->actingAs($comment->user)->post(route('avatar.store'), ['avatar' => UploadedFile::fake()->image('me.png')]);

        $this->actingAs(User::factory()->create())
            ->get(route('lists.show', $comment->task->task_list_id))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('comments.0.avatar', $comment->user->fresh()->avatar));
    }
}
