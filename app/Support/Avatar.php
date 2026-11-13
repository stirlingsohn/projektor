<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Profile pictures: cropped to a square and saved anew, which keeps them
 * small and drops metadata such as the location of a phone photo.
 */
class Avatar
{
    public const DISK = 'local';

    private const SIZE = 256;

    public static function store(User $user, UploadedFile $file): void
    {
        $source = imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        abort_if($source === false, 422);
        $source = self::upright($source, $file);

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($square, 0, 0, imagecolorallocate($square, 255, 255, 255));
        imagecopyresampled($square, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), self::SIZE, self::SIZE, $side, $side);

        ob_start();
        imagejpeg($square, null, 85);
        $path = "avatars/{$user->id}-".Str::random(8).'.jpg';
        Storage::disk(self::DISK)->put($path, (string) ob_get_clean());

        self::remove($user);
        $user->forceFill(['avatar_path' => $path])->save();
    }

    /** Phone photos are often stored sideways with a note on how to turn them. */
    private static function upright(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || $file->getMimeType() !== 'image/jpeg') {
            return $image;
        }

        $angle = match (@exif_read_data($file->getRealPath())['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle ? imagerotate($image, $angle, 0) : $image;
    }

    public static function remove(User $user): void
    {
        if ($user->avatar_path) {
            Storage::disk(self::DISK)->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }
    }
}
