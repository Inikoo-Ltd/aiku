<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 7 Apr 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\CRM\Prospect\Mailshots\Filters;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;

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
        $count = is_array($sentEmailTimes['value'] ?? null) ? ($sentEmailTimes['value']['count'] ?? null) : null;

        if ($isSentEmailTimesActive) {
            $query->where('prospects.number_dispatched_emails', $count);
        }

        return $query;
    }
}
