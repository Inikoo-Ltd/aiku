<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Dec 2024 01:14:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Masters\MasterShop;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterShopHydrateMasterFamilies
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
            'number_master_product_categories_type_family' => $masterShop->getMasterFamilies()->count(),
            'number_current_master_product_categories_type_family' => $masterShop->getMasterFamilies()->where('status', true)->count(),
        ];



        $masterShop->stats()->update($stats);
    }


}
