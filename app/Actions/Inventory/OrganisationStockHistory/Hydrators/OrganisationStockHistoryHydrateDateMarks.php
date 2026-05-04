<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Apr 2026 11:05:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrganisationStockHistory\Hydrators;

use App\Models\Inventory\OrganisationStockHistory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationStockHistoryHydrateDateMarks implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(?int $organisationStockHistory): int
    {
        return $organisationStockHistory ?? 0;
    }

    public function handle(?int $organisationStockHistoryId): void
    {
        if (!$organisationStockHistoryId) {
            return;
        }
        $organisationStockHistory = OrganisationStockHistory::find($organisationStockHistoryId);
        if (!$organisationStockHistory) {
            return;
        }

        $organisationStockHistory->update(
            [
                'is_week'  => $organisationStockHistory->date->isFriday(),
                'is_month' => $organisationStockHistory->date->isLastOfMonth(),
                'is_year'  => $organisationStockHistory->date->isEndOfYear(),
            ]
        );
    }


}
