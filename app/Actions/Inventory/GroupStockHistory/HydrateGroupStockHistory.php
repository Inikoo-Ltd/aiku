<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Apr 2026 20:35:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\GroupStockHistory;

use App\Actions\HydrateModel;
use App\Actions\Inventory\GroupStockHistory\Hydrators\GroupStockHistoryHydrateFromOrgStockHistories;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\GroupStockHistory;

class HydrateGroupStockHistory extends HydrateModel
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:group_stock_histories';

    public function __construct()
    {
        $this->model = GroupStockHistory::class;
    }


    public function handle(GroupStockHistory $groupStockHistory): void
    {
        GroupStockHistoryHydrateFromOrgStockHistories::run($groupStockHistory->id);
    }


}
