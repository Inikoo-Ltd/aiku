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

        if (!is_array($interestTags) && !is_null($interestTags)) {
            $interestTags = [$interestTags];
        }

        if (empty($interestTags)) {
            return $query;
        }

        $query->whereHas('tags', function (Builder $q) use ($interestTags) {
            $q->whereIn('tags.id', $interestTags);
        });

        return $query;
    }
}
