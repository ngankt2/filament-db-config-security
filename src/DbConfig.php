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

    private static function decodeSetting($settingValue)
    {
        if (!$settingValue) {
            return null;
        }
        $settingValue = config('db-config.encrypt') ? _decrypt_static($settingValue) : $settingValue;
        return json_decode($settingValue, true);
    }

    private static function encodeSetting(mixed $value): string
    {
        try {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('Unable to serialize value to JSON: ' . $e->getMessage(), 0, $e);
        }

        return config('db-config.encrypt') ? _encrypt_static($encoded) : $encoded;
    }

    public static function get(string $key, mixed $default = null, $group = 'default'): mixed
    {

        $cacheKey = static::getCacheKey($key);
        $cacheTtl = config('db-config.cache.ttl');

        $callback = fn() => static::fetchConfig($key, $group);

        // Use remember() with TTL if provided, otherwise rememberForever()
        $data = ($cacheTtl > 0)
            ? Cache::remember($cacheKey, $cacheTtl * 60, $callback)
            : Cache::rememberForever($cacheKey, $callback);

        return self::decodeSetting($data) ?? $default;
    }

    public static function getWithoutCache(string $key, mixed $default = null, $group = 'default'): mixed
    {
        $data = static::fetchConfig($key, $group);
        return self::decodeSetting($data) ?? $default;
    }

    /**
     * Store a configuration value in the database and invalidate the related cache.
     *
     * The key must use dotted notation "group.setting". The value is JSON-serialized
     * before being persisted.
     *
     * @param string $key The configuration key.
     * @param mixed $value The configuration value.
     * @param bool $merge Whether to merge with existing value (true) or reset/override (false).
     */
    public static function set(string $key, mixed $value, string $group = 'default', bool $merge = true): void
    {
        $cacheKey = static::getCacheKey($key, $group);

        Cache::forget($cacheKey);

        $existingData = static::fetchConfig($key, $group);
        $existingValue = self::decodeSetting($existingData);

        if ($merge && is_array($existingValue) && is_array($value)) {
            // Merge recursively: override duplicate keys, keep non-duplicate
            $mergedValue = array_replace_recursive($existingValue, $value);
            static::storeConfig($key, $mergedValue, $group);
        } else {
            // Reset/override
            static::storeConfig($key, $value, $group);
        }
    }

    /**
     * Fetch configuration data for a specific group and setting from the database.
     *
     * @param string $key
     * @param string $group The group name.
     * @return array<string, mixed>
     */
    protected static function fetchConfig(string $key, string $group): null|string
    {
        $tableName = config('db-config.table_name', 'db_config');

        /** @var \stdClass|null $item */
        $item = DB::table($tableName)
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        if ($item === null || !property_exists($item, 'settings')) {
            return null;
        }
        return $item->settings;
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

        $encoded = self::encodeSetting($value);

        DB::table($tableName)->upsert(
            [
                [
                    'group'      => $group,
                    'key'        => $key,
                    'settings'   => $encoded,
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
