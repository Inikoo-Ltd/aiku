<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:57:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Goods\MasterShop;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterShopHydrateMasterDepartments
{
    use AsAction;
    use WithEnumStats;

    private MasterShop $masterShop;

    public function __construct(MasterShop $masterShop)
    {
        $this->masterShop = $masterShop;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->masterShop->id))->dontRelease()];
    }

    public function handle(MasterShop $masterShop): void
    {
        $stats = [
            'number_master_product_categories_type_department' => $masterShop->getMasterDepartments()->count(),
            'number_current_master_product_categories_type_department' => $masterShop->getMasterDepartments()->where('status', true)->count(),
        ];



        $masterShop->stats()->update($stats);
    }


}
