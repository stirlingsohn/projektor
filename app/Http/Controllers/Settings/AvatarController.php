<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Avatar;
use App\Support\StoredImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AvatarController extends Controller
{
    public function show(User $user): BinaryFileResponse
    {
        Gate::authorize('view-avatar', $user);
        abort_unless($user->avatar_path, 404);

        return StoredImage::response(Avatar::DISK, $user->avatar_path);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['avatar' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:10240']]);

        Avatar::store($request->user(), $request->file('avatar'));

        return to_route('profile.edit');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Avatar::remove($request->user());

        return to_route('profile.edit');
    }
}
