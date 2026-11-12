<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_document_opens_right_away()
    {
        $project = Project::factory()->create();

        $response = $this->actingAs(User::factory()->create())->post(route('projects.documents.store', $project));

        $document = Document::sole();
        $response->assertRedirect(route('documents.show', $document));
        $this->assertSame('Unbenanntes Dokument', $document->title);
    }

    public function test_title_and_content_are_saved()
    {
        $document = Document::factory()->create();
        $content = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Strophe 1']]]]];

        $this->actingAs(User::factory()->create())
            ->put(route('documents.update', $document), ['title' => 'Songtext', 'content' => $content])
            ->assertSessionHasNoErrors();

        $this->assertSame('Songtext', $document->fresh()->title);
        $this->assertSame($content, $document->fresh()->content);
    }

    public function test_documents_are_shown_with_their_content()
    {
        $document = Document::factory()->create(['content' => ['type' => 'doc']]);

        $this->actingAs(User::factory()->create())
            ->get(route('documents.show', $document))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('documents/Show')
                ->where('document.content.type', 'doc')
                ->where('canEdit', true));
    }

    public function test_clients_cannot_open_documents_of_other_projects()
    {
        $document = Document::factory()->create();

        $this->actingAs(User::factory()->client()->create())
            ->get(route('documents.show', $document))
            ->assertForbidden();
    }

    public function test_deleting_a_document_returns_to_its_project()
    {
        $document = Document::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('documents.destroy', $document))
            ->assertRedirect(route('projects.show', $document->project_id));

        $this->assertModelMissing($document);
    }
}
