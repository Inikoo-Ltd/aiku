<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Mar 2026 18:50:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock;

use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateAllOrgStocksDayOrgStockHistory implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'stock-history';

    public function getJobUniqueId(string $date): string
    {
        return $date;
    }

    public function handle(string $date): void
    {
        $date = Carbon::parse($date);

        OrgStock::orderBy('id')->chunk(100, function ($orgStocks) use ($date) {
            /** @var OrgStock $orgStock */
            foreach ($orgStocks as $orgStock) {
                CalculateDayOrgStockHistory::run($orgStock->id, $date);
            }
        });
    }


}
