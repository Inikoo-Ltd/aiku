<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Jan 2026 21:06:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Enums\UI\Procurement\OrgStockTabsEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

trait WithOrgStock
{
    private Organisation|OrgStockFamily $parent;

    public function asController(Organisation $organisation, Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->parent = $organisation;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab($this->tabsEnum::values());

        return $this->handle($orgStock);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inStockFamily(Organisation $organisation, Warehouse $warehouse, OrgStockFamily $orgStockFamily, OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->parent = $orgStockFamily;
        $this->initialisationFromWarehouse($warehouse, $request)->withTab(OrgStockTabsEnum::values());

        return $this->handle($orgStock);
    }

}
