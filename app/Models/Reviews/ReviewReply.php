<?php

namespace App\Models\Reviews;

use App\Enums\Catalogue\Review\ReviewReplyReplierTypeEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewReply extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_public' => 'boolean',
        'status' => ReviewStatusEnum::class,
        'replier_type' => ReviewReplyReplierTypeEnum::class,
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function productReview(): BelongsTo
    {
        return $this->belongsTo(ProductReview::class, 'reviewable_id')
            ->where('review_replies.reviewable_type', 'product_reviews');
    }

    public function shopReview(): BelongsTo
    {
        return $this->belongsTo(ShopReview::class, 'reviewable_id')
            ->where('review_replies.reviewable_type', 'shop_reviews');
    }

    public function productCategoryReview(): BelongsTo
    {
        return $this->belongsTo(ProductCategoryReview::class, 'reviewable_id')
            ->where('review_replies.reviewable_type', 'product_category_reviews');
    }
}
