<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Procurement\PurchaseOrder;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexPurchaseOrderSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:purchase_orders';


    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(PurchaseOrder::class, $reindex, $reset);
    }


}
