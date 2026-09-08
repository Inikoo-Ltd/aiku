<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Jun 2023 21:00:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgStockHydrateValueInLocations implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'hydrators-slave';

    public string $commandSignature = 'hydrate:org_stocks_value_in_locations';

    public function getJobUniqueId(OrgStock $orgStock): string
    {
        return $orgStock->id;
    }

    public function handle(OrgStock $orgStock): void
    {
        $orgStock->update([
            'value_in_locations' => ($orgStock->quantity_in_locations ?? 0) * ($orgStock->sku_value ?? 0)
        ]);
    }

    public function asCommand(Command $command): int
    {
        $updated = DB::update("
            UPDATE org_stocks
            SET value_in_locations = COALESCE(quantity_in_locations, 0) * COALESCE(sku_value, 0)
            WHERE value_in_locations IS DISTINCT FROM ROUND(COALESCE(quantity_in_locations, 0) * COALESCE(sku_value, 0), 2)
        ");

        $command->info("Org stocks value_in_locations updated: $updated");

        return 0;
    }
}
