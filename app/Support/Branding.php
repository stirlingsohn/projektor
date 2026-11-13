<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * The company name and logo the owner can set, shown in place of
 * "Projektor" in the app, the browser tab and emails.
 */
class Branding
{
    public const DISK = 'local';

    public static function name(): string
    {
        return self::setting('company_name') ?: config('projektor.name');
    }

    public static function logoPath(): ?string
    {
        return self::setting('logo_path');
    }

    /** The logo URL, versioned so browsers pick up a new logo right away. */
    public static function logoUrl(): ?string
    {
        $path = self::logoPath();

        return $path ? route('branding.logo', ['v' => substr(md5($path), 0, 8)]) : null;
    }

    public static function storeLogo(UploadedFile $file): void
    {
        $old = self::logoPath();
        $path = $file->storeAs('branding', 'logo-'.Str::random(8).'.'.strtolower($file->extension()), self::DISK);
        Setting::put('logo_path', $path);

        if ($old) {
            Storage::disk(self::DISK)->delete($old);
        }
    }

    public static function removeLogo(): void
    {
        if ($old = self::logoPath()) {
            Storage::disk(self::DISK)->delete($old);
        }
        Setting::put('logo_path', null);
    }

    /**
     * Use the company name as the app and sender name, e.g. in email headers.
     */
    public static function apply(): void
    {
        $name = self::name();
        config(['app.name' => $name, 'mail.from.name' => $name]);
    }

    private static function setting(string $key): ?string
    {
        try {
            return Setting::get($key);
        } catch (Throwable) {
            // No database yet, e.g. while installing.
            return null;
        }
    }
}
