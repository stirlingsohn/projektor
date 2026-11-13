<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Branding;
use App\Support\StoredImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Company name and logo, set by the owner.
 */
class BrandingController extends Controller
{
    public function edit(): Response
    {
        Gate::authorize('manage-branding');

        return Inertia::render('settings/Branding', [
            'companyName' => Setting::get('company_name'),
            'defaultName' => config('projektor.name'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manage-branding');

        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:60'],
            'logo' => ['nullable', 'file', 'max:2048', 'mimes:png,jpg,jpeg,webp,svg'],
        ]);

        Setting::put('company_name', trim($validated['company_name'] ?? '') ?: null);
        if ($request->hasFile('logo')) {
            Branding::storeLogo($request->file('logo'));
        }

        return to_route('branding.edit');
    }

    public function destroyLogo(): RedirectResponse
    {
        Gate::authorize('manage-branding');

        Branding::removeLogo();

        return to_route('branding.edit');
    }

    /** The logo is public: it shows on the login page and in emails. */
    public function logo(): HttpResponse
    {
        $path = Branding::logoPath();
        abort_unless($path, 404);

        return StoredImage::response(Branding::DISK, $path);
    }
}
