<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Jul 2026 18:40:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews\Hydrators\Concerns;

use App\Enums\Catalogue\Review\ReviewStatusEnum;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

trait BuildsReviewStats
{
    protected function emptyReviewStats(): array
    {
        return [
            'number_reviews' => 0,
            'number_reviews_pending' => 0,
            'number_reviews_approved' => 0,
            'number_reviews_rejected' => 0,
            'number_rating_1' => 0,
            'number_rating_2' => 0,
            'number_rating_3' => 0,
            'number_rating_4' => 0,
            'number_rating_5' => 0,
            'average_rating_main' => 0,
            'average_rating_a' => 0,
            'average_rating_b' => 0,
            'average_rating_c' => 0,
            'average_rating_d' => 0,
            'average_rating_e' => 0,
        ];
    }

    protected function buildReviewStats(EloquentBuilder|QueryBuilder $baseQuery): array
    {
        $statusCounts = (clone $baseQuery)
            ->selectRaw('review_status, count(*) as aggregate')
            ->groupBy('review_status')
            ->pluck('aggregate', 'review_status');

        $ratingBuckets = (clone $baseQuery)
            ->selectRaw('
                count(case when rating_main >= 1 and rating_main < 2 then 1 end) as number_rating_1,
                count(case when rating_main >= 2 and rating_main < 3 then 1 end) as number_rating_2,
                count(case when rating_main >= 3 and rating_main < 4 then 1 end) as number_rating_3,
                count(case when rating_main >= 4 and rating_main < 5 then 1 end) as number_rating_4,
                count(case when rating_main >= 5 and rating_main <= 5 then 1 end) as number_rating_5
            ')
            ->first();

        return [
            'number_reviews' => (int) (clone $baseQuery)->count(),
            'number_reviews_pending' => (int) ($statusCounts[ReviewStatusEnum::PENDING->value] ?? 0),
            'number_reviews_approved' => (int) ($statusCounts[ReviewStatusEnum::APPROVED->value] ?? 0),
            'number_reviews_rejected' => (int) ($statusCounts[ReviewStatusEnum::REJECTED->value] ?? 0),
            'number_rating_1' => (int) ($ratingBuckets?->number_rating_1 ?? 0),
            'number_rating_2' => (int) ($ratingBuckets?->number_rating_2 ?? 0),
            'number_rating_3' => (int) ($ratingBuckets?->number_rating_3 ?? 0),
            'number_rating_4' => (int) ($ratingBuckets?->number_rating_4 ?? 0),
            'number_rating_5' => (int) ($ratingBuckets?->number_rating_5 ?? 0),
            'average_rating_main' => round((float) ((clone $baseQuery)->avg('rating_main') ?? 0), 2),
            'average_rating_a' => round((float) ((clone $baseQuery)->avg('rating_a') ?? 0), 2),
            'average_rating_b' => round((float) ((clone $baseQuery)->avg('rating_b') ?? 0), 2),
            'average_rating_c' => round((float) ((clone $baseQuery)->avg('rating_c') ?? 0), 2),
            'average_rating_d' => round((float) ((clone $baseQuery)->avg('rating_d') ?? 0), 2),
            'average_rating_e' => round((float) ((clone $baseQuery)->avg('rating_e') ?? 0), 2),
        ];
    }
}
