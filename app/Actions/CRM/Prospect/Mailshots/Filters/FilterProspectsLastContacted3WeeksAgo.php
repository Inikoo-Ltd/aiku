<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 7 Apr 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots\Filters;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;

class FilterProspectsLastContacted3WeeksAgo
{
    /**
     * Apply the "Last Contacted 3 Weeks Ago" filter to the query.
     *
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $lastContacted3Weeks = Arr::get($filters, 'last_contacted_3_weeks_ago');
        $isLastContacted3WeeksActive = is_array($lastContacted3Weeks) ? ($lastContacted3Weeks['value'] ?? false) : $lastContacted3Weeks;

        if ($isLastContacted3WeeksActive) {
            $threeWeeksAgo = now()->subWeeks(3);
            $query->where(function ($q) use ($threeWeeksAgo) {
                $q->whereNull('prospects.last_contacted_at')
                    ->orWhere('prospects.last_contacted_at', '<=', $threeWeeksAgo);
            });
        }

        return $query;
    }
}
