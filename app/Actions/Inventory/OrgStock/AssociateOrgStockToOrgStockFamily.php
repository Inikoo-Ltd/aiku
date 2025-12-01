<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Dec 2025 11:08:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateOrgStocks;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use Lorisleiva\Actions\Concerns\AsAction;

class AssociateOrgStockToOrgStockFamily
{
    use AsAction;

    public function handle(OrgStock $orgStock, OrgStockFamily $orgStockFamily): OrgStock
    {
        $orgStock->update(['org_stock_family_id' => $orgStockFamily->id]);
        OrgStockFamilyHydrateOrgStocks::dispatch($orgStockFamily);

        return $orgStock;
    }
}
