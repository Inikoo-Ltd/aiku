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

class FilterProspectsSentEmail3Times
{
    /**
     * Apply the "Sent Email 3 Times" filter to the query.
     *
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $sentEmail3Times = Arr::get($filters, 'sent_email_3_times');
        $isSentEmail3TimesActive = is_array($sentEmail3Times) ? ($sentEmail3Times['value'] ?? false) : $sentEmail3Times;

        if ($isSentEmail3TimesActive) {
            $query->whereExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('prospect_has_dispatched_emails')
                    ->whereRaw('prospect_has_dispatched_emails.prospect_id = prospects.id')
                    ->groupBy('prospect_has_dispatched_emails.prospect_id')
                    ->havingRaw('COUNT(*) >= 3');
            });
        }

        return $query;
    }
}
