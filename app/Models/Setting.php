<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App-wide settings as key/value pairs, e.g. the branding.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    /** @var array<string, ?string>|null */
    private static ?array $loaded = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        self::$loaded ??= self::query()->pluck('value', 'key')->all();

        return self::$loaded[$key] ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        self::$loaded = null;
    }

    /** Forget the values read in this request, e.g. between tests. */
    public static function flush(): void
    {
        self::$loaded = null;
    }
}
