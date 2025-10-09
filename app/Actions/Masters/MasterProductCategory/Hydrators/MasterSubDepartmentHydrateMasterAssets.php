<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Aug 2025 15:32:37 Central European Summer Time, Malaga, Spain
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

class MasterSubDepartmentHydrateMasterAssets implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(MasterProductCategory $masterSubDepartment): string
    {
        return $masterSubDepartment->id;
    }

    public function handle(MasterProductCategory $masterSubDepartment): void
    {
        if ($masterSubDepartment->type != MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
            return;
        }

        $stats = [
            'number_master_assets' => $masterSubDepartment->masterAssets()->count(),
            'number_current_master_assets' => $masterSubDepartment->masterAssets()->where('status', true)->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'current_master_assets',
                field: 'type',
                enum: MasterAssetTypeEnum::class,
                models: MasterAsset::class,
                where: function ($q) use ($masterSubDepartment) {
                    $q->where('master_sub_department_id', $masterSubDepartment->id)->where('status', true);
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
                where: function ($q) use ($masterSubDepartment) {
                    $q->where('master_sub_department_id', $masterSubDepartment->id);
                }
            )
        );


        $masterSubDepartment->stats()->update($stats);
    }


}
