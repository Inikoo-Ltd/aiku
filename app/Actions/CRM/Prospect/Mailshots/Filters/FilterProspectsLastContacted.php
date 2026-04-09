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
            $mode = is_array($lastContacted) ? ($lastContacted['mode'] ?? 'three_weeks_ago') : 'three_weeks_ago';
            $customDate = is_array($lastContacted) ? ($lastContacted['custom_date'] ?? null) : null;

            // Use custom_date if value exists
            if (array_key_exists('custom_date', $lastContacted)) {
                if ($customDate) {
                    $targetDate = Carbon::parse($customDate)->startOfDay();
                } else {
                    // If custom_date exists but is null, don't apply date filter
                    return $query;
                }
            } else {
                // Handle string-based presets
                $weeks = match ($mode) {
                    'one_week_ago' => 1,
                    'two_weeks_ago' => 2,
                    'three_weeks_ago' => 3,
                    default => 3,
                };
                $targetDate = now()->subWeeks($weeks)->startOfDay();
            }

            // Use exact date matching instead of <=
            $query->where(function ($q) use ($targetDate) {
                $q->whereNull('prospects.last_contacted_at')
                    ->orWhereDate('prospects.last_contacted_at', $targetDate);
            });
        }

        return $query;
    }
}
