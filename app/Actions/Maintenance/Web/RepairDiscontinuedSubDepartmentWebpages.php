<?php

/*
 * author Louis Perez
 * created on 22-06-2026-11h-08m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\Traits\WithManageWebpageState;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Console\Command;

class RepairDiscontinuedSubDepartmentWebpages
{
    use WithActionUpdate;
    use WithManageWebpageState;

    public function handle(ProductCategory $productCategory): void
    {
        $this->handleWebpageState($productCategory);
    }

    public string $commandSignature = 'repair:discontinued-sub-department-webpages';

    public function asCommand(Command $command): void
    {
        $query = ProductCategory::query()
            ->with(['webpage', 'shop'])
            ->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
            ->whereHas('shop', function ($q) {
                $q->where('is_aiku', true);
            })
            ->whereIn('state', [
                ProductCategoryStateEnum::DISCONTINUED,
                ProductCategoryStateEnum::IN_PROCESS,
            ])
            ->whereHas('webpage', function ($q) {
                $q->where('state', WebpageStateEnum::LIVE);
            });

        $count = (clone $query)->count();

        $command->info("Found {$count} discontinued/in_process sub-departments with LIVE webpages.");

        $query->orderBy('id')
            ->chunk(500, function ($productCategories) use ($command) {
                foreach ($productCategories as $productCategory) {

                    $webpage = $productCategory->webpage;

                    $this->handle($productCategory);

                    $command->info(sprintf(
                        "Repaired family: %s | family_state: %s | webpage: %s | webpage_state: %s",
                        $productCategory->slug,
                        $productCategory->state?->value ?? 'N/A',
                        $webpage->slug,
                        $webpage?->state?->value ?? 'N/A'
                    ));
                }
            });

        $command->info("Repair completed.");
    }
}
