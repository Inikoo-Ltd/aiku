<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DebugWebhooks extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data' => 'array'
    ];

    protected $attributes = [
        'data' => '{}'
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
