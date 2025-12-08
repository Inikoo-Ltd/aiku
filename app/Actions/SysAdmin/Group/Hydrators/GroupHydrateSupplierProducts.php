<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 16:49:46 Central Indonesia Time, (Pizarro) Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateSupplierProducts;
use App\Actions\Traits\WithEnumStats;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateSupplierProducts implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithHydrateSupplierProducts;

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }

    public function handle(Group $group): void
    {
        $stats = $this->getSupplierProductsStats($group);

        $group->supplyChainStats()->update($stats);
    }
}
