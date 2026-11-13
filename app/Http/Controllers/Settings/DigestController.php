<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Subscribing to the daily and weekly task emails.
 */
class DigestController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Emails', [
            'digests' => $request->user()->only('digest_daily', 'digest_weekly'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->user()->update($request->validate([
            'digest_daily' => ['required', 'boolean'],
            'digest_weekly' => ['required', 'boolean'],
        ]));

        return to_route('digests.edit');
    }
}
