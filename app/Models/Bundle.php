<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bundle extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'settings' => 'array'
    ];

    protected $attributes = [
        'data' => '{}',
        'settings' => '{}'
    ];
}
