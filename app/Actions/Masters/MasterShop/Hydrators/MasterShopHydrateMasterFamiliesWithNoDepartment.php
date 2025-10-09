<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Aug 2025 15:09:41 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Masters\MasterShop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterShopHydrateMasterFamiliesWithNoDepartment implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(MasterShop $masterShop): string
    {
        return $masterShop->id;
    }

    public function handle(MasterShop $masterShop): void
    {
        $stats = [
            'number_master_families_no_master_department' => $masterShop->getMasterFamilies()->whereNull('master_department_id')->count(),
        ];

        $masterShop->stats()->update($stats);
    }


}
