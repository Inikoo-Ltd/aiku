<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateMasterProductCategories implements ShouldBeUnique
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
            'number_master_product_categories' => DB::table('master_product_categories')
                ->where('group_id', $group->id)
                ->whereNull('deleted_at')
                ->count(),
            'number_current_master_product_categories' => DB::table('master_product_categories')
                ->where('group_id', $group->id)
                ->where('status', true)
                ->whereNull('deleted_at')
                ->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'master_product_categories',
                field: 'type',
                enum: MasterProductCategoryTypeEnum::class,
                models: MasterProductCategory::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        foreach (MasterProductCategoryTypeEnum::cases() as $type) {
            $stats['number_current_master_product_categories_type_' . $type->value] = DB::table('master_product_categories')
                ->where('group_id', $group->id)
                ->where('status', true)
                ->where('type', $type->value)
                ->whereNull('deleted_at')
                ->count();
        }

        $group->goodsStats()->update($stats);
    }
}
