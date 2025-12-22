<?php

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Catalogue\Product|null $product
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductOrderingIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductOrderingIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductOrderingIntervals query()
 * @mixin \Eloquent
 */
class ProductOrderingIntervals extends Model
{
    protected $table = 'product_ordering_intervals';

    protected $guarded = [];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
