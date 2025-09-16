<?php

namespace Ngankt2\DbConfig\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Ngankt2\DbConfig
 *\DbConfig
 */
class DbConfig extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Ngankt2\DbConfig\DbConfig::class;
    }
}
