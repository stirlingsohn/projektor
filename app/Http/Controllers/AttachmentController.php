<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class AttachmentController extends Controller
{
    /**
     * Types the browser may show inline. Anything else, HTML and SVG in
     * particular, is only ever downloaded so it cannot run scripts here.
     */
    private const INLINE_TYPES = '/^(image\/(png|jpe?g|gif|webp|avif)|audio\/.+|video\/(mp4|webm|ogg|quicktime)|application\/pdf)$/';

    public function store(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'max:'.config('projektor.max_upload_mb') * 1024],
            'task_id' => [
                'nullable',
                Rule::exists(Task::class, 'id')->whereIn('task_list_id', $project->taskLists()->pluck('id')),
            ],
        ]);

        $task = isset($validated['task_id']) ? Task::find($validated['task_id']) : null;
        Gate::authorize(...($task ? ['editTasks', $task->taskList] : ['contribute', $project]));

        foreach ($validated['files'] as $file) {
            $project->attachments()->create([
                'task_id' => $validated['task_id'] ?? null,
                'user_id' => $request->user()->id,
                'path' => $file->store("attachments/{$project->id}", Attachment::DISK),
                'name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            ]);
        }

        return back();
    }

    /** The file itself, for previews; unsafe types are downloaded instead. */
    public function show(Attachment $attachment): BinaryFileResponse
    {
        Gate::authorize('view', $attachment);

        $inline = preg_match(self::INLINE_TYPES, $attachment->mime_type) === 1;

        return $this->respond($attachment, $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    public function download(Attachment $attachment): BinaryFileResponse
    {
        Gate::authorize('view', $attachment);

        return $this->respond($attachment, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    public function destroy(Attachment $attachment): RedirectResponse
    {
        Gate::authorize('delete', $attachment);

        $attachment->delete();

        return back();
    }

    private function respond(Attachment $attachment, string $disposition): BinaryFileResponse
    {
        $response = response()->file(Storage::disk(Attachment::DISK)->path($attachment->path), [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
        $response->setContentDisposition($disposition, $attachment->name, Str::ascii($attachment->name));

        return $response;
    }
}
