<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 31 Aug 2024 10:58:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\Hydrators;

use App\Models\Inventory\OrgStock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrgStockHydrateMovements implements ShouldBeUnique
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
                'number_org_stock_movements' => DB::table('org_stock_movements')->where('org_stock_id', $orgStock->id)->count(),
            ]
        );
    }


}
