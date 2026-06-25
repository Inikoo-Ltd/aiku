<?php

namespace App\Models\Reviews;

use App\Models\Catalogue\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopReviewStat extends Model
{
    protected $table = 'shop_review_stats';

    protected $guarded = [];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
