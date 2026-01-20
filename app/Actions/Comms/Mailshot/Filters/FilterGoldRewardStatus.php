<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class FilterGoldRewardStatus
{
    /**
     * Apply the Gold Reward Status filter.
     *
     * @param Builder $query
     * @param string $status 'gold' or 'non_gold'
     * @return Builder
     */
    public function apply($query, $status)
    {

        $thirtyDaysAgo = Carbon::now()->subDays(30);

        if ($status === 'gold') {
            $query->whereNotNull('last_invoiced_at')
                ->where('last_invoiced_at', '>=', $thirtyDaysAgo);
        } elseif ($status === 'non_gold') {
            $query->whereNotNull('last_invoiced_at')
                ->where('last_invoiced_at', '<', $thirtyDaysAgo);
        }

        return $query;
    }
}
