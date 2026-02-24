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
use Illuminate\Console\Command;
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

        $activeMasterAssets   = $masterFamily->masterAssets()->where('status', true)->count();
        $inactiveMasterAssets = $masterFamily->masterAssets()->where('status', false)->count();


        $status = $this->getStatus($activeMasterAssets, $inactiveMasterAssets, $numberShops, $numberShopsActive);


        UpdateMasterProductCategory::run($masterFamily, ['status' => $status]);
    }

    public function getStatus($activeMasterAssets, $inactiveMasterAssets, $numberShops, $numberShopsActive): bool
    {
        if ($activeMasterAssets == 0 && $inactiveMasterAssets == 0 && $numberShops == 0 && $numberShopsActive == 0) {
            return true;
        }

        if ($activeMasterAssets == 0 && $numberShopsActive == 0) {
            return false;
        }

        return true;
    }


    public function getCommandSignature(): string
    {
        return 'master_product_categories:hydrate_status {masterProductCategory?}';
    }

    public function asCommand(Command $command): int
    {
        if ($masterProductCategorySlug = $command->argument('masterProductCategory')) {
            $masterProductCategory = MasterProductCategory::where('slug', $masterProductCategorySlug)->firstOrFail();
            $this->handle($masterProductCategory);
            $command->info('Updated '.$masterProductCategory->name);

            return 0;
        }

        $masterFamilies = MasterProductCategory::where('type', MasterProductCategoryTypeEnum::FAMILY);

        $command->withProgressBar($masterFamilies->count(), function ($bar) use ($masterFamilies) {
            $masterFamilies->chunk(100, function ($chunk) use ($bar) {
                foreach ($chunk as $masterFamily) {
                    $this->handle($masterFamily);
                    $bar->advance();
                }
            });
        });

        $command->newLine();

        return 0;
    }


}
