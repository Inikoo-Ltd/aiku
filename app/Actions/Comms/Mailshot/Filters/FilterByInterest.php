<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;

class FilterByInterest
{
    /**
     * Apply the "By Interest" filter to the query.
     *
     * @param Builder|SpatieQueryBuilder $query
     * @param array $filters
     * @return Builder|SpatieQueryBuilder
     */
    public function apply($query, array $filters)
    {
        $interestFilter = Arr::get($filters, 'by_interest');
        $interestTags = is_array($interestFilter) ? ($interestFilter['value'] ?? []) : [];

        // Extract IDs from the nested structure
        $tagIds = [];
        if (isset($interestTags['ids']) && is_array($interestTags['ids'])) {
            $tagIds = $interestTags['ids'];
        } elseif (is_array($interestTags)) {
            // If it's already a flat array of IDs
            $tagIds = $interestTags;
        }

        if (!empty($tagIds)) {
            $query->whereHas('tags', function (Builder $q) use ($tagIds) {
                $q->whereIn('tags.id', $tagIds);
            });
        }

        return $query;
    }
}
