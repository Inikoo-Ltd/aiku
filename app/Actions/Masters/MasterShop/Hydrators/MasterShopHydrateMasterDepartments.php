<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:57:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop\Hydrators;

use App\Models\Masters\MasterShop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterShopHydrateMasterDepartments implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(MasterShop $masterShop): string
    {
        return $masterShop->id;
    }


    public function handle(MasterShop $masterShop): void
    {
        $stats = [
            'number_master_product_categories_type_department'         => $masterShop->getMasterDepartments()->count(),
            'number_current_master_product_categories_type_department' => $masterShop->getMasterDepartments()->where('status', true)->count(),
        ];

        $masterShop->stats()->update($stats);
    }


}
