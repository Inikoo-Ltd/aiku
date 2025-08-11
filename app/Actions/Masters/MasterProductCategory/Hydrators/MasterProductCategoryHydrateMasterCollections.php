<?php

/*
 * author Arya Permana - Kirin
 * created on 28-05-2025-09h-22m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Masters\MasterProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Collection\CollectionProductsStatusEnum;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Catalogue\MasterCollection\MasterCollectionStateEnum;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterProductCategoryHydrateMasterCollections implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;


    public function getJobUniqueId(MasterProductCategory $masterProductCategory): string
    {
        return $masterProductCategory->id;
    }

    public function handle(MasterProductCategory $masterProductCategory): void
    {
        if ($masterProductCategory->type == MasterProductCategoryTypeEnum::FAMILY) {
            return;
        }

        $stats = [
            'number_master_collections' => $masterProductCategory->masterCollections()->count(),
        ];

        $count = $masterProductCategory->masterCollections()->selectRaw("master_collections.state, count(*) as total")
            ->groupBy('master_collections.state')
            ->pluck('total', 'master_collections.state')->all();
        foreach (MasterCollectionStateEnum::cases() as $case) {
            $stats["number_collections_state_".$case->snake()] = Arr::get($count, $case->value, 0);
        }
        foreach (CollectionProductsStatusEnum::cases() as $case) {
            $stats["number_collections_products_status_".$case->snake()] = Arr::get($count, $case->value, 0);
        }
        $stats['number_current_collections'] = $stats['number_collections_state_active'] + $stats['number_collections_products_status_discontinuing'];


        $masterProductCategory->stats()->update($stats);
    }


}
