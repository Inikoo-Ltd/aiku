<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Web\Banner\BannerStateEnum;
use App\Models\SysAdmin\Group;
use App\Models\Web\Banner;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateBanners
{
    use AsAction;
    use WithEnumStats;

    public function handle(Group $group): void
    {
        $stats = [
            'number_banners' => $group->banners()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'banners',
                field: 'state',
                enum: BannerStateEnum::class,
                models: Banner::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        $group->webStats()->update($stats);
    }
}
