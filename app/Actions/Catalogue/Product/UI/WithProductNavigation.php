<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 21 Dec 2025 12:18:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\Traits\Actions\WithNavigation;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Models\Catalogue\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;

trait WithProductNavigation
{
    use WithNavigation;

    protected function applyNavigationFilters(Builder $query, Model $model, ActionRequest $request): void
    {
        /** @var Product $product */
        $product   = $model;
        $routeName = request()->route()->getName();

        if ($routeName == 'grp.org.fulfilments.show.catalogue.show') {
            $query->where('shop_id', $product->shop->fulfilment->id);

            return;
        }

        $query->where('shop_id', $product->shop_id);


        if (in_array($routeName, [
            'grp.org.shops.show.catalogue.departments.show.products.show',
            'grp.org.shops.show.catalogue.departments.show.products.edit'
        ])) {
            $query->where('department_id', $product->department_id);

            return;
        }


        if (in_array($routeName, [
            'grp.org.shops.show.catalogue.sub_departments.show.products.show',
            'grp.org.shops.show.catalogue.sub_departments.show.products.edit'
        ])) {
            $query->where('sub_department_id', $product->sub_department_id);

            return;
        }

        if (in_array($routeName, [
            'grp.org.shops.show.catalogue.families.show.products.show',
            'grp.org.shops.show.catalogue.families.show.products.edit',
            'grp.org.shops.show.catalogue.departments.show.families.show.products.show',
            'grp.org.shops.show.catalogue.departments.show.families.show.products.edit',
            'grp.org.shops.show.catalogue.sub_departments.show.families.show.products.show',
            'grp.org.shops.show.catalogue.sub_departments.show.families.show.products.edit'
        ])) {
            $query->where('family_id', $product->family_id);

            return;
        }


        if (str_contains($routeName, 'out_of_stock_products')) {
            $query->where('status', ProductStatusEnum::OUT_OF_STOCK);
        }
        if (str_contains($routeName, 'orphan_products')) {
            $query->whereNull('family_id');
        }
        if (str_contains($routeName, 'current_products')) {
            $query->whereIn('state', [
                ProductStateEnum::ACTIVE,
                ProductStateEnum::DISCONTINUING
            ]);
        }

        if (str_contains($routeName, 'in_process_products')) {
            $query->where('state', ProductStateEnum::IN_PROCESS);
        }
        if (str_contains($routeName, 'discontinued_products')) {
            $query->where('state', ProductStateEnum::DISCONTINUED);
        }
    }

    protected function getNavigationLabel(Model $model): string
    {
        /** @var Product $model */
        return $model->name;
    }

    protected function getNavigationRouteParameters(Model $model, string $routeName): array
    {
        /** @var Product $product */
        $product = $model;

        return match ($routeName) {
            'grp.org.shops.show.catalogue.products.all_products.show',
            'grp.org.shops.show.catalogue.products.all_products.edit',
            'grp.org.shops.show.catalogue.products.orphan_products.show',
            'grp.org.shops.show.catalogue.products.orphan_products.edit',
            'grp.org.shops.show.catalogue.products.out_of_stock_products.show',
            'grp.org.shops.show.catalogue.products.out_of_stock_products.edit',
            'grp.org.shops.show.catalogue.products.current_products.show',
            'grp.org.shops.show.catalogue.products.current_products.edit',
            'grp.org.shops.show.catalogue.products.in_process_products.show',
            'grp.org.shops.show.catalogue.products.in_process_products.edit',
            'grp.org.shops.show.catalogue.products.discontinued_products.show',
            'grp.org.shops.show.catalogue.products.discontinued_products.edit'
            => [
                'organisation' => $this->organisation->slug,
                'shop'         => $product->shop->slug,
                'product'      => $product->slug,
            ],
            'grp.org.shops.show.catalogue.departments.show.families.show.products.show',
            'grp.org.shops.show.catalogue.departments.show.families.show.products.edit'
            => [
                'organisation' => $this->organisation->slug,
                'shop'         => $product->shop->slug,
                'department'   => $product->department?->slug,
                'family'       => $product->family?->slug,
                'product'      => $product->slug,
            ],
            'grp.org.shops.show.catalogue.departments.show.products.show',
            'grp.org.shops.show.catalogue.departments.show.products.edit'
            => [
                'organisation' => $this->organisation->slug,
                'shop'         => $product->shop->slug,
                'department'   => $product->department?->slug,
                'product'      => $product->slug,
            ],
            'grp.org.shops.show.catalogue.families.show.products.show',
            'grp.org.shops.show.catalogue.families.show.products.edit'
            => [
                'organisation' => $this->organisation->slug,
                'shop'         => $product->shop->slug,
                'family'       => $product->family?->slug,
                'product'      => $product->slug,
            ],
            'grp.org.shops.show.catalogue.sub_departments.show.families.show.products.show',
            'grp.org.shops.show.catalogue.sub_departments.show.families.show.products.edit'
            => [
                'organisation'  => $this->organisation->slug,
                'shop'          => $product->shop->slug,
                'subDepartment' => $product->subDepartment?->slug,
                'family'        => $product->family?->slug,
                'product'       => $product->slug,
            ],
            'grp.org.shops.show.catalogue.sub_departments.show.products.show',
            'grp.org.shops.show.catalogue.sub_departments.show.products.edit'
            => [
                'organisation'  => $this->organisation->slug,
                'shop'          => $product->shop->slug,
                'subDepartment' => $product->subDepartment?->slug,
                'product'       => $product->slug,
            ],

            'grp.org.fulfilments.show.catalogue.show'
            => [
                'organisation' => $this->organisation->slug,
                'fulfilment'   => $product->shop->fulfilment->slug,
                'product'      => $product->slug,
            ],
            default => request()->route()->originalParameters(),
        };
    }
}
