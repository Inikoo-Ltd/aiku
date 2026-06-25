<?php

namespace App\Models\Reviews;

use App\Models\Masters\MasterProductCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterProductCategoryReviewStat extends Model
{
    protected $table = 'master_product_category_review_stats';

    protected $guarded = [];

    public function masterProductCategory(): BelongsTo
    {
        return $this->belongsTo(MasterProductCategory::class);
    }
}
