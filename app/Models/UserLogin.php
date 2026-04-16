<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserLogin extends Model
{
    protected $guarded = [];

    protected $casts = [
        'location' => 'array',
        'device_type' => 'array',
        'platform' => 'array',
        'browser' => 'array',
        'datetime' => 'datetime'
    ];

    protected $attributes = [
        'location' => '{}',
        'device_type' => '{}',
        'platform' => '{}',
        'browser' => '{}'
    ];
}
