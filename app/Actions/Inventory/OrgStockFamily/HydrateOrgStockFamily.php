<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Aug 2024 12:03:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockFamily;

use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateOrgStocks;
use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateSalesIntervals;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\OrgStockFamily;

class HydrateOrgStockFamily
{
    use WithHydrateCommand;
    public string $commandSignature = 'hydrate:org_stock_families {organisations?*} {--s|slugs=} ';

    public function __construct()
    {
        $this->model = OrgStockFamily::class;
    }

    public function handle(OrgStockFamily $orgStockFamily): void
    {
        OrgStockFamilyHydrateOrgStocks::run($orgStockFamily);
        OrgStockFamilyHydrateSalesIntervals::run($orgStockFamily);
    }




}
