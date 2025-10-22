<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Oct 2025 17:05:20 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\Hydrators;

use App\Actions\Masters\MasterProductCategory\UpdateMasterProductCategory;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterFamilyHydrateStatus implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(MasterProductCategory $masterFamily): string
    {
        return $masterFamily->id;
    }

    public function handle(MasterProductCategory $masterFamily): void
    {
        if ($masterFamily->type != MasterProductCategoryTypeEnum::FAMILY) {
            return;
        }

        $numberShops       = 0;
        $numberShopsActive = 0;

        $families = ProductCategory::where('master_product_category_id', $masterFamily->id)->where('type', MasterProductCategoryTypeEnum::FAMILY)->get();

        /** @var ProductCategory $family */
        foreach ($families as $family) {
            $numberShops++;
            if ($family->state == ProductCategoryStateEnum::ACTIVE) {
                $numberShopsActive++;
            }
        }


        if ($numberShops == 0) {
            $status = true;
        } elseif ($numberShopsActive == 0) {
            $status = false;
        } else {
            $status = true;
        }

        UpdateMasterProductCategory::run($masterFamily, ['status' => $status]);
    }


}
