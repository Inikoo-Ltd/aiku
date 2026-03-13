<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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

    public function bundleable(): MorphTo
    {
        return $this->morphTo();
    }
}
