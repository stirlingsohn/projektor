<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

/**
 * Documents can be read by everyone who sees the project's contents and
 * edited by those who may contribute to it.
 */
class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $user->can('viewContents', $document->project);
    }

    public function update(User $user, Document $document): bool
    {
        return $user->can('contribute', $document->project);
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->isOwner();
    }
}
