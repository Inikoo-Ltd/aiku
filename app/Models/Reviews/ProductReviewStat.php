<?php

namespace App\Models\Reviews;

use App\Models\Catalogue\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReviewStat extends Model
{
    protected $table = 'product_review_stats';

    protected $guarded = [];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
