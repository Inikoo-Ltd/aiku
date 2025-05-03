<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:59:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Actions\Inventory\WithHydrateOrgStockSalesIntervals;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemSalesTypeEnum;
use App\Models\Inventory\OrgStock;

class OrgStockHydrateSalesIntervals
{
    use WithHydrateOrgStockSalesIntervals;


    public function getJobUniqueId(OrgStock $orgStock, ?array $intervals = null, ?array $doPreviousPeriods = null, ?DeliveryNoteItemSalesTypeEnum $onlyProcessSalesType = null): string
    {
        return $this->getStockableJobUniqueId(
            $orgStock,
            $intervals,
            $doPreviousPeriods,
            $onlyProcessSalesType
        );
    }

    public function handle(OrgStock $orgStock, ?array $intervals = null, ?array $doPreviousPeriods = null, ?DeliveryNoteItemSalesTypeEnum $onlyProcessSalesType = null): void
    {
        $this->handleStockable(
            $orgStock,
            $intervals,
            $doPreviousPeriods,
            $onlyProcessSalesType
        );
    }

}
