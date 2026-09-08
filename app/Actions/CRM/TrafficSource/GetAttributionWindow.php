<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource;

use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAttributionWindow
{
    use AsAction;

    /**
     * How many days after a touch that channel may still claim a customer's revenue.
     *
     * A shop can override the group default through `settings.marketing.attribution_window_days`,
     * because reorder cycles differ: a dropshipping shop converts in days, wholesale in months.
     * Zero or negative disables the window, which restores the old behaviour of crediting a
     * channel with everything a customer ever spent - only ever useful for comparison.
     */
    public function handle(Shop $shop): int
    {
        $shopWindow = Arr::get($shop->settings ?? [], 'marketing.attribution_window_days');

        if (is_numeric($shopWindow)) {
            return (int) $shopWindow;
        }

        return (int) config('marketing.attribution_window_days', 90);
    }
}
