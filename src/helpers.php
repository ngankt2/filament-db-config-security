<?php

if (!function_exists('db_config')) {
    function db_config(string $key, mixed $default = null, string $group = 'default', $encrypt = false): mixed
    {
        return \Ngankt2\DbConfig\DbConfig::get($key, $default, $group, $encrypt);
    }
}

if (!function_exists('db_config_encrypt')) {
    function db_config_encrypt(string $key, mixed $default = null, string $group = 'default'): mixed
    {
        return \Ngankt2\DbConfig\DbConfig::get($key, $default, $group, true);
    }
}
