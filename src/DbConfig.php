<?php

declare(strict_types=1);

namespace Ngankt2\DbConfig;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Manages reading, writing, and caching of configuration settings stored in the database.
 *
 * Retrieves configuration entries from a configurable table, uses the application cache
 * with a configurable TTL, and provides helpers to fetch all settings for a group and
 * to obtain the group's last updated timestamp.
 */
class DbConfig
{
    /**
     * Retrieve a configuration value from the database, using cache when available.
     *
     * Accepts dotted keys in the form "group.setting" or "group.setting.subkey".
     * Returns the provided default when the key is absent.
     *
     * @param string $key The configuration key.
     * @param mixed $default The default value to return if the configuration key is not found.
     * @return mixed The configuration value.
     */
    public static function get(string $key, mixed $default = null, $group = 'default'): mixed
    {

        $cacheKey = static::getCacheKey($key);
        $cacheTtl = config('db-config.cache.ttl');

        $callback = fn() => static::fetchConfig($key, $group);

        // Use remember() with TTL if provided, otherwise rememberForever()
        $data = ($cacheTtl > 0)
            ? Cache::remember($cacheKey, $cacheTtl * 60, $callback)
            : Cache::rememberForever($cacheKey, $callback);

        return $data ?? $default;
    }
    public static function getWithoutCache(string $key, mixed $default = null, $group = 'default'): mixed
    {
        $data = static::fetchConfig($key, $group);
        return $data ?? $default;
    }

    /**
     * Store a configuration value in the database and invalidate the related cache.
     *
     * The key must use dotted notation "group.setting". The value is JSON-serialized
     * before being persisted.
     *
     * @param string $key The configuration key.
     * @param mixed $value The configuration value.
     */
    public static function set(string $key, mixed $value, string $group = 'default'): void
    {

        $cacheKey = static::getCacheKey($key, $group);

        Cache::forget($cacheKey);

        static::storeConfig($key, $value, $group);
    }

    /**
     * Retrieve all settings for a specific group from the storage table.
     *
     * Returns an associative array where keys are setting names and values are the
     * decoded JSON values or null.
     *
     * @param string $group The group name.
     * @return array<string, mixed|null>|null Associative array mapping setting keys to decoded values or null; returns an empty array if the group has no settings.
     */
    public static function getGroup(string $group): ?array
    {
        $settings = [];

        $tableName = config('db-config.table_name', 'db_config');

        DB::table($tableName)->where('group', $group)->get()->each(function (\stdClass $setting) use (&$settings) {
            $settings[$setting->key] = json_decode($setting->settings, true);
        });

        return $settings;
    }

    /**
     * Return the formatted last updated timestamp for a specific group.
     *
     * Performs an aggregated query to obtain the maximum `updated_at` timestamp
     * for the requested group and returns it formatted in the requested timezone
     * and format. Returns null if no timestamp is available or on parse errors.
     *
     * usage: DbConfig::getLastUpdated('general');
     *
     * @param string $group The group name.
     * @param string $format The date format (default: 'F j, Y, g:i a').
     * @param string $timezone The timezone (default: 'UTC').
     * @return string|null The formatted last updated timestamp or null if not found.
     */
    public static function getGroupLastUpdatedAt(string $group, string $format = 'F j, Y, g:i a', string $timezone = 'UTC'): ?string
    {
        $tableName = config('db-config.table_name', 'db_config');

        // Un'unica query con aggregazione
        $timestamp = DB::table($tableName)
            ->where('group', $group)
            ->max('updated_at');

        if (empty($timestamp)) {
            return null;
        }

        try {
            $fromTz = config('app.timezone', 'UTC');
            $dt = $timestamp instanceof Carbon
                ? $timestamp
                : Carbon::parse($timestamp, $fromTz);

            return $dt->setTimezone($timezone)->format($format);
        } catch (\Throwable $e) {
            return null;
        }
    }


    /**
     * Fetch configuration data for a specific group and setting from the database.
     *
     * @param string $key
     * @param string $group The group name.
     * @return array<string, mixed>
     */
    protected static function fetchConfig(string $key, string $group): array
    {
        $tableName = config('db-config.table_name', 'db_config');

        /** @var \stdClass|null $item */
        $item = DB::table($tableName)
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        if ($item === null || !property_exists($item, 'settings')) {
            return [];
        }
        $settingValue = config('db-config.encrypt') ? _decrypt_static($item->settings) : $item->settings;
        return json_decode($settingValue, true);
    }

    /**
     * Persist a configuration value for a given group and setting into the storage table.
     *
     * The provided value is JSON-encoded before persisting. A RuntimeException is thrown
     * if encoding fails. This method also updates timestamps and performs an upsert.
     *
     * @param string $key
     * @param mixed $value The value to be stored.
     */
    protected static function storeConfig(string $key, mixed $value, $group = 'default'): void
    {
        $tableName = config('db-config.table_name', 'db_config');

        try {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Unable to serialize value to JSON: ' . $e->getMessage(), 0, $e);
        }

        DB::table($tableName)->upsert(
            [
                [
                    'group' => $group,
                    'key' => $key,
                    'settings' => config('db-config.encrypt') ? _encrypt_static($encoded) : $encoded,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            ['group', 'key'], // unique by
            ['settings', 'updated_at'] // columns to update on duplicate
        );
    }

    protected static function getCacheKey(string $key, $group = 'default'): string
    {
        $prefix = config('db-config.cache.prefix', 'db-config');

        return "{$prefix}.{$group}.$key";
    }
}
