<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $bundle_id
 * @property string $item_type
 * @property int $item_id
 * @property int $quantity
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Bundle $bundle
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
