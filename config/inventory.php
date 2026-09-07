<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Aug 2026 23:50:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

return [
    /*
     | Official stock valuation method: fifo, wac or lpp.
     | Drives sku_value (margins, product cost, dashboards), which valuation
     | column is shown by default in stock tables, the legends, and the
     | dormant-stock figure. After changing it run per organisation:
     | org_stocks:sku_value, hydrate:org_stocks_value_in_locations and
     | hydrate:organisation_stock_histories.
     */
    'official_valuation_method' => env('OFFICIAL_STOCK_VALUATION_METHOD', 'fifo'),
];
