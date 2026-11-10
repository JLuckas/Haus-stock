<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Store-wide preferences edited in the app (e.g. the target margin).
 *
 * @property string $key
 * @property string|null $value
 */
class Setting extends Model
{
    public const TARGET_MARGIN = 'target_margin_percent';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever("setting:{$key}", fn () => self::query()->find($key)?->value);

        return $value ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        Cache::forget("setting:{$key}");
    }
}
