<?php

namespace App\Actions\Catalogue\Review\Hydrators\Concerns;

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
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $ratingCounts = (clone $baseQuery)
            ->selectRaw('rating_main, count(*) as aggregate')
            ->groupBy('rating_main')
            ->pluck('aggregate', 'rating_main');

        return [
            'number_reviews' => (int) (clone $baseQuery)->count(),
            'number_reviews_pending' => (int) ($statusCounts[ReviewStatusEnum::Pending->value] ?? 0),
            'number_reviews_approved' => (int) ($statusCounts[ReviewStatusEnum::Approved->value] ?? 0),
            'number_reviews_rejected' => (int) ($statusCounts[ReviewStatusEnum::Rejected->value] ?? 0),
            'number_rating_1' => (int) ($ratingCounts[1] ?? 0),
            'number_rating_2' => (int) ($ratingCounts[2] ?? 0),
            'number_rating_3' => (int) ($ratingCounts[3] ?? 0),
            'number_rating_4' => (int) ($ratingCounts[4] ?? 0),
            'number_rating_5' => (int) ($ratingCounts[5] ?? 0),
            'average_rating_main' => round((float) ((clone $baseQuery)->avg('rating_main') ?? 0), 2),
            'average_rating_a' => round((float) ((clone $baseQuery)->avg('rating_a') ?? 0), 2),
            'average_rating_b' => round((float) ((clone $baseQuery)->avg('rating_b') ?? 0), 2),
            'average_rating_c' => round((float) ((clone $baseQuery)->avg('rating_c') ?? 0), 2),
            'average_rating_d' => round((float) ((clone $baseQuery)->avg('rating_d') ?? 0), 2),
            'average_rating_e' => round((float) ((clone $baseQuery)->avg('rating_e') ?? 0), 2),
        ];
    }
}
