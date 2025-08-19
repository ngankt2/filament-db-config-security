<?php

namespace Inerba\DbConfig\Models;

use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    protected $table = 'db_config';

    protected $fillable = [
        'group',
        'key',
        'settings',
    ];

    /** @var array<string,string> */
    protected $casts = [
        'settings' => 'array',
    ];
}
