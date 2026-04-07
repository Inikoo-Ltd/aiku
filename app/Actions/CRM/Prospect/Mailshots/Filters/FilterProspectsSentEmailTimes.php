<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 7 Apr 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots\Filters;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FilterProspectsSentEmailTimes
{
    /**
     * Apply the "Sent Email N Times" filter to the query.
     *
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $sentEmailTimes = Arr::get($filters, 'sent_email_times');
        $isSentEmailTimesActive = is_array($sentEmailTimes) ? ($sentEmailTimes['value'] ?? false) : $sentEmailTimes;
        $count = is_array($sentEmailTimes) ? ($sentEmailTimes['count'] ?? 3) : 3;

        if ($isSentEmailTimesActive) {
            $query->whereExists(function ($subQuery) use ($count) {
                $subQuery->select(DB::raw(1))
                    ->from('prospect_has_dispatched_emails')
                    ->whereRaw('prospect_has_dispatched_emails.prospect_id = prospects.id')
                    ->groupBy('prospect_has_dispatched_emails.prospect_id')
                    ->havingRaw('COUNT(*) >= ?', [$count]);
            });
        }

        return $query;
    }
}
