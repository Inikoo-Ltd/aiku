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
    use WithTouchCausality;

    /** Kept identical in the `marketing_attributable_invoices` view. */
    public const ATTRIBUTABLE_DATE = "COALESCE((SELECT o.date FROM orders o WHERE o.id = invoices.order_id), invoices.date)";

    /**
     * Revenue is judged by when the customer *ordered*, not when the paperwork was raised. Invoicing
     * lags orders by a day or two, and a touch landing inside that lag was collecting orders that had
     * already been placed - 93% of all attributed revenue in prod, on the day this was found. The
     * invoice date is the fallback for invoices with no linked order.
     *
     * Rows with no recorded touch date are legacy and are left in rather than silently dropped, so
     * historic attribution does not vanish the day this ships.
     *
     * @param \Illuminate\Database\Query\JoinClause $join
     */
    protected function constrainToAttributionWindow($join, int $window, string $pivotAlias = 'p'): void
    {
        $this->constrainToTouchWindow($join, self::ATTRIBUTABLE_DATE, $window, $pivotAlias);
    }
}
