<?php

/*
 * author Arya Permana - Kirin
 * created on 28-05-2025-09h-22m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
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
            'number_collections' => $productCategory->childrenCollections()->count(),
        ];

        $productCategory->stats()->update($stats);
    }


}
