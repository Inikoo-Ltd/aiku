<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property-read \App\Models\Bundle|null $bundle
 * @property-read Model|\Eloquent $item
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BundleItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BundleItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BundleItem query()
 * @mixin \Eloquent
 */
class BundleItem extends Model
{
    protected $guarded = [];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    public function item(): MorphTo
    {
        return $this->morphTo();
    }
}
