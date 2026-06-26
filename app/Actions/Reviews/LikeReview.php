<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Jun 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews;

use App\Models\Reviews\Review;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class LikeReview
{
    use AsObject;

    public function handle(Review $review, string $target, bool $isLike, int $customerId): Review
    {
        $cacheKey = "review_reaction:{$review->id}:{$customerId}:{$target}";

        if (Cache::has($cacheKey)) {
            return $review;
        }

        $column = match (true) {
            $target === 'review' && $isLike  => 'likes',
            $target === 'review' && !$isLike => 'dislikes',
            $target === 'reply'  && $isLike  => 'replay_likes',
            $target === 'reply'  && !$isLike => 'replay_dislikes',
        };

        DB::table('reviews')->where('id', $review->id)->increment($column);
        Cache::put($cacheKey, true, now()->addDays(30));

        return $review->refresh();
    }
}
