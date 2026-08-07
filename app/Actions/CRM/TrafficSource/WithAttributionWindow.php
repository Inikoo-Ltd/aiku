<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

/**
 * One definition of which revenue a marketing touch may claim, shared by every place that answers
 * that question: the dashboard, both stats hydrators, and the email performance panel. They used to
 * be separate copies, and a channel's campaigns could then legitimately out-earn the channel itself.
 *
 * The `marketing_attributable_invoices` SQL view mirrors this rule for the reporting views; keep the
 * two in step.
 */
trait WithAttributionWindow
{
    /**
     * Revenue counts when it was invoiced after the touch that earned it, and no later than the
     * window allows. Rows with no recorded touch date are legacy and are left in rather than
     * silently dropped, so historic attribution does not vanish the day this ships.
     *
     * @param \Illuminate\Database\Query\JoinClause $join
     */
    protected function constrainToAttributionWindow($join, int $window, string $pivotAlias = 'p'): void
    {
        $join->where(function ($query) use ($window, $pivotAlias) {
            $query->whereNull($pivotAlias.'.first_touch_at')
                ->orWhere(function ($inWindow) use ($window, $pivotAlias) {
                    $inWindow->whereColumn('invoices.date', '>=', $pivotAlias.'.first_touch_at');

                    if ($window > 0) {
                        $inWindow->whereRaw(
                            "invoices.date <= {$pivotAlias}.last_touch_at + (? || ' days')::interval",
                            [$window]
                        );
                    }
                });
        });
    }
}
