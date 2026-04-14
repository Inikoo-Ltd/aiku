<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 7 Apr 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots\Filters;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Carbon\Carbon;

class FilterProspectsLastContacted
{
    /**
     * Apply the "Last Contacted" filter to the query.
     *
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $filter = Arr::get($filters, 'last_contacted');

        $source = is_array($filter) && is_array($filter['value'] ?? null)
            ? $filter['value']
            : (is_array($filter) ? $filter : []);

        if (empty($source['value'])) {
            return $query;
        }

        $targetDate = isset($source['custom_date'])
            ? Carbon::parse($source['custom_date'])->startOfDay()
            : now()->subWeeks(match ($source['mode'] ?? 'three_weeks_ago') {
                'one_week_ago'  => 1,
                'two_weeks_ago' => 2,
                default         => 3,
            })->startOfDay();

        return $query->where(function ($q) use ($targetDate) {
            $q->whereNull('prospects.last_contacted_at')
                ->orWhereDate('prospects.last_contacted_at', $targetDate);
        });
    }
}
