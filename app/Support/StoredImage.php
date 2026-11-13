<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves an uploaded image (logo, profile picture) from private storage.
 */
class StoredImage
{
    public static function response(string $disk, string $path): BinaryFileResponse
    {
        $storage = Storage::disk($disk);
        abort_unless($storage->exists($path), 404);

        return response()->file($storage->path($path), [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            // SVGs may contain scripts; never run them when opened directly.
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
