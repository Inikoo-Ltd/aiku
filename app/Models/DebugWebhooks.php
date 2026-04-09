<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property-read Model|\Eloquent $model
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DebugWebhooks newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DebugWebhooks newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DebugWebhooks query()
 * @mixin \Eloquent
 */
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
