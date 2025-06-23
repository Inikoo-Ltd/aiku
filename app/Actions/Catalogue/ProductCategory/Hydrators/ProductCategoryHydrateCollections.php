<?php

/*
 * author Arya Permana - Kirin
 * created on 28-05-2025-09h-22m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductCategoryHydrateCollections implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;


    public function getJobUniqueId(ProductCategory $productCategory): string
    {
        return $productCategory->id;
    }

    public function handle(ProductCategory $productCategory): void
    {
        if ($productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            return;
        }

        $stats = [
            'number_collections' => $productCategory->collections()->count(),
        ];

        $count = $productCategory->collections()->selectRaw("collections.state, count(*) as total")
            ->where('model_type', 'ProductCategory')
            ->where('model_id', $productCategory->id)
            ->groupBy('collections.state')
            ->pluck('total', 'collections.state')->all();
        foreach (CollectionStateEnum::cases() as $case) {
            $stats["number_collections_state_".$case->snake()] = Arr::get($count, $case->value, 0);
        }
        $stats['number_current_collections'] = $stats['number_collections_state_active'] + $stats['number_collections_state_discontinuing'];


        $productCategory->stats()->update($stats);
    }


}
