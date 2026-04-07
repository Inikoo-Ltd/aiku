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
        $lastContacted = Arr::get($filters, 'last_contacted');
        $isLastContactedActive = is_array($lastContacted) ? ($lastContacted['value'] ?? false) : $lastContacted;

        if ($isLastContactedActive) {
            $mode = is_array($lastContacted) ? ($lastContacted['mode'] ?? 3) : 3;
            $customDate = is_array($lastContacted) ? ($lastContacted['custom_date'] ?? null) : null;

            if ($mode === 'custom' && $customDate) {
                $cutoffDate = Carbon::parse($customDate);
            } else {
                $weeks = is_numeric($mode) ? (int) $mode : 3;
                $cutoffDate = now()->subWeeks($weeks);
            }

            $query->where(function ($q) use ($cutoffDate) {
                $q->whereNull('prospects.last_contacted_at')
                    ->orWhere('prospects.last_contacted_at', '<=', $cutoffDate);
            });
        }

        return $query;
    }
}
