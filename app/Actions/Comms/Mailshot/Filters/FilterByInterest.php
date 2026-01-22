<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\QueryBuilder as SpatieQueryBuilder;

class FilterByInterest
{
    /**
     * Apply the "By Interest" filter to the query.
     *
     * @param Builder|SpatieQueryBuilder $query
     * @param array|int|string $tagIds
     * @return Builder|SpatieQueryBuilder
     */
    public function apply($query, $tagIds)
    {
        if (empty($tagIds)) {
            return $query;
        }

        $tagIds = is_array($tagIds) ? $tagIds : [$tagIds];

        $query->whereHas('tags', function (Builder $q) use ($tagIds) {
            $q->whereIn('tags.id', $tagIds);
        });

        return $query;
    }
}
