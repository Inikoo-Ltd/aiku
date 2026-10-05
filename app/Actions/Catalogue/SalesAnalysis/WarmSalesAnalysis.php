<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Sep 2026 04:05:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\SalesAnalysis;

use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Departments and sub-departments cover thousands of products and take seconds to analyse, so
 * their default Sales analysis, which the Overview teaser also reads, is worked out overnight and
 * cached for the day. Families and below take well under a second and are left to the first visit.
 */
class WarmSalesAnalysis implements ShouldBeUnique
{
    use AsAction;

    public string $commandSignature = 'sales-analysis:warm';
    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(string $type, int $id): string
    {
        return $type.':'.$id;
    }

    public function handle(string $type, int $id): void
    {
        $scope = match ($type) {
            'master' => ($category = MasterProductCategory::find($id)) ? SalesAnalysisScope::forMasterCategory($category) : null,
            default => ($category = ProductCategory::find($id)) ? SalesAnalysisScope::forProductCategory($category) : null,
        };
        if (!$scope) {
            return;
        }

        GetSalesAnalysis::make()->handle($scope, []);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $masterIds = MasterProductCategory::whereIn('type', [MasterProductCategoryTypeEnum::DEPARTMENT, MasterProductCategoryTypeEnum::SUB_DEPARTMENT])
            ->where('status', true)
            ->pluck('id');
        $shopIds = ProductCategory::whereIn('type', [ProductCategoryTypeEnum::DEPARTMENT, ProductCategoryTypeEnum::SUB_DEPARTMENT])
            ->whereIn('state', [ProductCategoryStateEnum::ACTIVE, ProductCategoryStateEnum::DISCONTINUING])
            ->pluck('id');

        $masterIds->each(fn ($id) => self::dispatch('master', $id));
        $shopIds->each(fn ($id) => self::dispatch('shop', $id));

        $command->info("Queued {$masterIds->count()} master and {$shopIds->count()} shop departments and sub-departments");

        return 0;
    }
}
