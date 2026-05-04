<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Mar 2026 18:50:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Stock;

use App\Models\Inventory\OrgStock;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateAllOrgStocksDayOrgStockHistory
{
    use AsAction;

    public string $jobQueue = 'stock-history';
    public int $jobTries = 3;
    public int $jobBackoff = 120;


    public function tags(): array
    {
        return ['stock_history'];
    }

    public function handle(?int $organisationId, string $date): void
    {
        if (!$organisationId) {
            return;
        }
        $organisation = OrgStock::find($organisationId);
        if (!$organisation) {
            return;
        }

        $date = Carbon::parse($date);

        OrgStock::where('organisation_id', $organisation->id)->orderBy('id')->chunk(100, function ($orgStocks) use ($date) {
            /** @var OrgStock $orgStock */
            foreach ($orgStocks as $orgStock) {
                CalculateDayOrgStockHistory::run($orgStock->id, $date);
            }
        });
    }


}
