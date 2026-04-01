<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Apr 2026 11:03:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrganisationStockHistory;

use App\Actions\Inventory\OrganisationStockHistory\Hydrators\OrganisationStockHistoryHydrateDateMarks;
use App\Actions\Inventory\OrganisationStockHistory\Hydrators\OrganisationStockHistoryHydrateFromOrgStockHistories;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Inventory\OrganisationStockHistory;

class HydrateOrganisationStockHistory
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:organisation_stock_histories {organisations?*} {--s|slugs=} ';

    public function __construct()
    {
        $this->model = OrganisationStockHistory::class;
    }


    public function handle(OrganisationStockHistory $organisationStockHistory): void
    {
        OrganisationStockHistoryHydrateFromOrgStockHistories::run($organisationStockHistory->id);
        OrganisationStockHistoryHydrateDateMarks::run($organisationStockHistory->id);
    }


}
