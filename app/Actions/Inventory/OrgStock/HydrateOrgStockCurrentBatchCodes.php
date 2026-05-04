<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 28 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateCurrentBatchCodes;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\OrgStock;

class HydrateOrgStockCurrentBatchCodes
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:org_stock_current_batch_codes {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = OrgStock::class;
    }

    public function handle(OrgStock $orgStock): void
    {
        OrgStockHydrateCurrentBatchCodes::run($orgStock);
    }
}
