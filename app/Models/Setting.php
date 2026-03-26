<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting:{$key}", 60, function () use ($key, $default) {
            return static::where('key', $key)->value('value') ?? $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting:{$key}");
    }

    /**
     * Bulk-fetch multiple settings in one query and warm the cache.
     * Subsequent Setting::get() calls for any of these keys will be served
     * from cache without an additional DB hit.
     *
     * Keys that are already cached are skipped so no redundant DB query is made.
     *
     * @param  string[]  $keys
     */
    public static function preload(array $keys): void
    {
        if (empty($keys)) {
            return;
        }

        $uncached = array_values(array_filter($keys, fn ($key) => ! Cache::has("setting:{$key}")));

        if (empty($uncached)) {
            return;
        }

        $settings = static::whereIn('key', $uncached)->pluck('value', 'key');

        foreach ($settings as $key => $value) {
            Cache::put("setting:{$key}", $value, 60);
        }
    }
}
