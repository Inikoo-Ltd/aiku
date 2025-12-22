<?php

namespace App\Models\Catalogue;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\Catalogue\Product|null $product
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductSalesIntervals newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductSalesIntervals newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductSalesIntervals query()
 * @mixin \Eloquent
 */
class ProductSalesIntervals extends Model
{
    protected $guarded = [];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
