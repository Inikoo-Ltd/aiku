<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Apr 2025 15:18:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterDepartmentHydrateMasterAssets implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(MasterProductCategory $masterDepartment): string
    {
        return $masterDepartment->id;
    }

    public function handle(MasterProductCategory $masterDepartment): void
    {
        if ($masterDepartment->type != MasterProductCategoryTypeEnum::DEPARTMENT) {
            return;
        }

        $stats = [
            'number_master_assets' => $masterDepartment->masterAssets()->count(),
            'number_current_master_assets' => $masterDepartment->masterAssets()->where('status', true)->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'current_master_assets',
                field: 'type',
                enum: MasterAssetTypeEnum::class,
                models: MasterAsset::class,
                where: function ($q) use ($masterDepartment) {
                    $q->where('master_department_id', $masterDepartment->id)->where('status', true);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'master_assets',
                field: 'type',
                enum: MasterAssetTypeEnum::class,
                models: MasterAsset::class,
                where: function ($q) use ($masterDepartment) {
                    $q->where('master_department_id', $masterDepartment->id);
                }
            )
        );


        $masterDepartment->stats()->update($stats);
    }


}
