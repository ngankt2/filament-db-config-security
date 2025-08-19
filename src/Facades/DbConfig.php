<?php

namespace Inerba\DbConfig\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Inerba\DbConfig
 *\DbConfig
 */
class DbConfig extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Inerba\DbConfig\DbConfig::class;
    }
}
