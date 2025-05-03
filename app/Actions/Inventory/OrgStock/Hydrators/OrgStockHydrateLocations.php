<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Jun 2023 20:55:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgStockHydrateLocations implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(OrgStock $orgStock): string
    {
        return $orgStock->id;
    }


    public function handle(OrgStock $orgStock): void
    {
        $orgStock->stats->update(
            [
                'number_locations' => $orgStock->locationOrgStocks()->count()
            ]
        );
    }


}
