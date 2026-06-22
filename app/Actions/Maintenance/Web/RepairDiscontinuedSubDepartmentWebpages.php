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
use Illuminate\Support\Arr;

class RepairDiscontinuedSubDepartmentWebpages
{
    use WithActionUpdate;

    public function handle(ProductCategory $productCategory, Command $command): void
    {
        if ($productCategory->webpage) {
            $result = CloseDiscontinuedWebpage::run($productCategory->webpage);

            if (Arr::has($result,'redirect_to'))
            {
                $command->info(sprintf(
                    'Redirecting discontinued sub-department %s to %s',
                    $productCategory->slug,
                    $result['redirect_to']
                ));
            } else {
                $command->error(sprintf(
                    'No redirect found for discontinued sub-department %s. Error: %s',
                    $productCategory->slug, 
                    data_get($result, 'error', 'n/a error message')
                ));
                exit;
            }
        }
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
            ])
            ->whereHas('webpage', function ($q) {
                $q->where('state', WebpageStateEnum::LIVE);
            });

        $count = (clone $query)->count();

        $command->info("Found $count discontinued/in_process sub-departments with LIVE webpages.");

        $query->orderBy('id')
            ->chunk(500, function ($productCategories) use ($command) {
                foreach ($productCategories as $productCategory) {

                    $webpage = $productCategory->webpage;

                    $this->handle($productCategory, $command);

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
