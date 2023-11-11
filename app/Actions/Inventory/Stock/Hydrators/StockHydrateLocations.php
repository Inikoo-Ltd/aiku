<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Jun 2023 20:55:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Stock\Hydrators;

use App\Actions\Traits\WithOrganisationJob;
use App\Models\Inventory\Stock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class StockHydrateLocations implements ShouldBeUnique
{
    use AsAction;
    use WithOrganisationJob;

    public function handle(Stock $stock): void
    {

        $stock->update(
            [
                'number_locations' => $stock->locations->count()
            ]
        );
    }

    public function getJobUniqueId(Stock $stock): int
    {
        return $stock->id;
    }
}
