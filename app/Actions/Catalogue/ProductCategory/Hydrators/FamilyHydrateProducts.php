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
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class FamilyHydrateProducts implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use HasGetProductCategoryState;

    public function getJobUniqueId(ProductCategory $family): string
    {
        return $family->id;
    }

    public function handle(ProductCategory $family): void
    {
        $stats = [
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


        $numberCurrentProductsActiveForSale = Product::where('family_id', $family->id)->where('is_for_sale', true)
            ->where('state', ProductStateEnum::ACTIVE)
            ->count();
        $numberCurrentProductsDiscontinuingForSale = Product::where('family_id', $family->id)->where('is_for_sale', true)
            ->where('state', ProductStateEnum::DISCONTINUING)
            ->count();

        $stats['number_current_products'] = $numberCurrentProductsActiveForSale + $numberCurrentProductsDiscontinuingForSale;


        UpdateProductCategory::make()->action(
            $family,
            [
                'state' => $this->getProductCategoryState($stats, $numberCurrentProductsActiveForSale, $numberCurrentProductsDiscontinuingForSale)
            ]
        );

        $family->stats()->update($stats);
    }


}
