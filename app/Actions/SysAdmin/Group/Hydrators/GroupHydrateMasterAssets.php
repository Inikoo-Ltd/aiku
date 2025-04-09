<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 02:58:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateMasterAssets implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }


    public function handle(Group $group): void
    {
        $stats = [
            'number_master_assets'         => DB::table('master_assets')->where('group_id', $group->id)->count(),
            'number_current_master_assets' => DB::table('master_assets')->where('group_id', $group->id)->where('status', true)->count()
        ];


        $group->goodsStats()->update($stats);
    }
}
