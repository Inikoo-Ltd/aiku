<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 21 Dec 2025 21:47:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Traits\Actions\WithNavigation;
use App\Models\Masters\MasterAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;

trait WithMasterProductNavigation
{
    use WithNavigation;

    protected function getNavigationComparisonColumn(): string
    {
        return 'code';
    }

    protected function applyNavigationFilters(Builder $query, Model $model, ActionRequest $request): void
    {
        /** @var MasterAsset $model */
        $query->where('master_shop_id', $model->master_shop_id);

        $routeName = $request->route()->getName();

        if (in_array($routeName, [
            'grp.masters.master_shops.show.master_departments.show.master_products.index',
            'grp.masters.master_shops.show.master_departments.show.master_products.show',
            'grp.masters.master_shops.show.master_departments.show.master_products.edit'
        ])) {
            $query->where('master_department_id', $model->master_department_id);
        } elseif (in_array($routeName, [
            'grp.masters.master_shops.show.master_families.master_products.index',
            'grp.masters.master_shops.show.master_families.master_products.show',
            'grp.masters.master_shops.show.master_families.master_products.edit'
        ])) {
            $query->where('master_family_id', $model->master_family_id);
        }
    }

    protected function getNavigationLabel(Model $model): string
    {
        /** @var MasterAsset $model */
        return $model->name;
    }

    protected function getNavigationRouteParameters(Model $model, string $routeName): array
    {
        /** @var MasterAsset $masterProduct */
        $masterProduct = $model;

        return match ($routeName) {
            'grp.masters.master_shops.show.master_products.index',
            'grp.masters.master_shops.show.master_products.show',
            'grp.masters.master_shops.show.master_products.edit'
            => [
                'masterShop'    => $masterProduct->masterShop->slug,
                'masterProduct' => $masterProduct->slug,
            ],
            'grp.masters.master_shops.show.master_departments.show.master_products.index',
            'grp.masters.master_shops.show.master_departments.show.master_products.show',
            'grp.masters.master_shops.show.master_departments.show.master_products.edit'
            => [
                'masterShop'       => $masterProduct->masterShop->slug,
                'masterDepartment' => $masterProduct->masterDepartment->slug,
                'masterProduct'    => $masterProduct->slug,
            ],
            'grp.masters.master_shops.show.master_families.master_products.index',
            'grp.masters.master_shops.show.master_families.master_products.show',
            'grp.masters.master_shops.show.master_families.master_products.edit'
            => [
                'masterShop'    => $masterProduct->masterShop->slug,
                'masterFamily'  => $masterProduct->masterFamily->slug,
                'masterProduct' => $masterProduct->slug,
            ],
            default => request()->route()->originalParameters(),
        };
    }
}
