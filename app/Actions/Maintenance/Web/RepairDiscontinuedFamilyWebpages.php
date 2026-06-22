<?php

/*
 * author Louis Perez
 * created on 22-06-2026-11h-08m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\CloseDiscontinuedWebpage;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Console\Command;

class RepairDiscontinuedFamilyWebpages
{
    use WithActionUpdate;

    public function handle(ProductCategory $productCategory): void
    {
        if ($productCategory->webpage) {
            CloseDiscontinuedWebpage::run($productCategory->webpage);
        }

    }

    public string $commandSignature = 'repair:discontinued-family-webpages';

    public function asCommand(Command $command): void
    {
        $query = ProductCategory::query()
            ->with(['webpage', 'shop'])
            ->where('type', ProductCategoryTypeEnum::FAMILY)
            ->whereHas('shop', function ($q) {
                $q->where('is_aiku', true);
            })
            ->whereIn('state', [
                ProductCategoryStateEnum::DISCONTINUED,
            ])
            ->whereHas('webpage', function ($q) {
                $q->where('state', WebpageStateEnum::LIVE);
            });

        $count = (clone $query)->count();

        $command->info("Found $count discontinued/in_process families with LIVE webpages.");

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
