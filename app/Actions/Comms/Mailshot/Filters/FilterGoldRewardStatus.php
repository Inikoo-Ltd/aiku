<?php

namespace App\Actions\Comms\Mailshot\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Arr;

class FilterGoldRewardStatus
{
    /**
     * Apply the Gold Reward Status filter.
     *
     * @param Builder $query
     * @param string $status 'gold' or 'non_gold'
     * @return Builder
     */
    public function apply($query, array $filters)
    {

        $goldFilter = Arr::get($filters, 'gold_reward_status');
        $goldStatus = is_array($goldFilter) ? ($goldFilter['value'] ?? null) : $goldFilter;

        $thirtyDaysAgo = Carbon::now()->subDays(30);

        if ($goldStatus === 'gold') {
            $query->whereNotNull('last_invoiced_at')
                ->where('last_invoiced_at', '>=', $thirtyDaysAgo);
        } elseif ($goldStatus === 'non_gold') {
            $query->whereNotNull('last_invoiced_at')
                ->where('last_invoiced_at', '<', $thirtyDaysAgo);
        }

        return $query;
    }
}
