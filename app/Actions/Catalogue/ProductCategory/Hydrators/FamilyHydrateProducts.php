<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Jun 2024 21:51:26 Central European Summer Time, Abu Dhabi Airport
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\Hydrators;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class FamilyHydrateProducts
{
    use AsAction;
    use WithEnumStats;
    use HasGetProductCategoryState;

    private ProductCategory $family;

    public function __construct(ProductCategory $family)
    {
        $this->family = $family;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->family->id))->dontRelease()];
    }

    public function handle(ProductCategory $family): void
    {
        $stats         = [
            'number_products' => $family->getproducts()->where('is_main', true)->whereNull('exclusive_for_customer_id')->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'products',
                field: 'state',
                enum: ProductStateEnum::class,
                models: Product::class,
                where: function ($q) use ($family) {
                    $q->where('is_main', true)->where('family_id', $family->id);
                }
            )
        );

        $stats['number_current_products'] = Arr::get($stats, 'number_products_state_active', 0) +
            Arr::get($stats, 'number_products_state_discontinuing', 0);

        UpdateProductCategory::make()->action(
            $family,
            [
                'state' => $this->getProductCategoryState($stats)
            ]
        );

        $family->stats()->update($stats);
    }




}
