<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

/**
 * The rule that decides whether a marketing touch may claim something that happened to the customer,
 * stated once and applied to whichever date column asks: an invoice's date, a registration's, an
 * order's. A touch claims only what came after it, and only within the shop's attribution window.
 *
 * [[WithAttributionWindow]] is the invoice-shaped face of this rule and delegates here, so revenue
 * and counts can never drift apart again the way separate copies once let a channel's campaigns
 * out-earn the channel itself.
 */
trait WithTouchCausality
{
    /**
     * Rows with no recorded touch date are legacy and are left in rather than silently dropped, so
     * historic attribution does not vanish the day this ships.
     *
     * @param \Illuminate\Database\Query\JoinClause|\Illuminate\Database\Query\Builder $query
     */
    protected function constrainToTouchWindow($query, string $dateColumn, int $window, string $pivotAlias = 'p'): void
    {
        $query->where(function ($query) use ($dateColumn, $window, $pivotAlias) {
            $query->whereNull($pivotAlias.'.first_touch_at')
                ->orWhere(function ($inWindow) use ($dateColumn, $window, $pivotAlias) {
                    $inWindow->whereColumn($dateColumn, '>=', $pivotAlias.'.first_touch_at');

                    if ($window > 0) {
                        $inWindow->whereRaw(
                            "{$dateColumn} <= {$pivotAlias}.last_touch_at + (? || ' days')::interval",
                            [$window]
                        );
                    }
                });
        });
    }
}
