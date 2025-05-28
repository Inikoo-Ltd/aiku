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
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductCategoryHydrateCollections
{
    use AsAction;
    use WithEnumStats;

    private ProductCategory $productCategory;

    public function __construct(ProductCategory $productCategory)
    {
        $this->productCategory = $productCategory;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->productCategory->id))->dontRelease()];
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
