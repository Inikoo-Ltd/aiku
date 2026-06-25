<?php

namespace App\Models\Reviews;

use App\Models\Catalogue\ProductCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCategoryReviewStat extends Model
{
    protected $table = 'product_category_review_stats';

    protected $guarded = [];

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }
}
