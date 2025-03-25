<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Jun 2024 10:19:11 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateShippings implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }
    public function handle(Group $group): void
    {

        $stats = [
            'number_assets_type_shipping' => $group->shippings()->count(),
        ];

        $group->catalogueStats()->update($stats);


    }

}
