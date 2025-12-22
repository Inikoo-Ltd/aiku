<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 21 Dec 2025 21:46:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Traits\Actions\WithNavigation;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;

trait WithMasterFamilyNavigation
{
    use WithNavigation;

    protected function getNavigationComparisonColumn(): string
    {
        return 'code';
    }

    protected function applyNavigationFilters(Builder $query, Model $model, ActionRequest $request): void
    {
        /** @var MasterProductCategory $model */
        $query->where('master_shop_id', $model->master_shop_id);
        $routeName = $request->route()->getName();

        if (in_array($routeName, [
            'grp.masters.master_shops.show.master_departments.show.master_families.index',
            'grp.masters.master_shops.show.master_departments.show.master_families.show',
            'grp.masters.master_shops.show.master_departments.show.master_families.edit',
            'grp.masters.master_shops.show.master_departments.show.master_families.show.master_products.index',
            'grp.masters.master_shops.show.master_departments.show.master_families.show.master_products.show',
            'grp.masters.master_shops.show.master_departments.show.master_families.show.master_products.edit',
        ])) {
            $query->where('master_department_id', $model->master_department_id);
        } elseif (in_array($routeName, [
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.master_products.index',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.master_products.show',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.master_products.edit',
            'grp.masters.master_shops.show.master_sub_departments.master_families.show',
            'grp.masters.master_shops.show.master_sub_departments.master_families.master_products.index'

    ])) {
            $query->where('master_sub_department_id', $model->master_sub_department_id);
        }

    }

    protected function getNavigationLabel(Model $model): string
    {
        /** @var MasterProductCategory $model */
        return $model->name;
    }

    protected function getNavigationRouteParameters(Model $model, string $routeName): array
    {
        /** @var MasterProductCategory $masterFamily */
        $masterFamily = $model;

        return match ($routeName) {
            'grp.masters.master_families.show' => [
                'masterFamily' => $masterFamily->slug,
            ],
            'grp.masters.master_shops.show.master_families.index',
            'grp.masters.master_shops.show.master_families.show',
            'grp.masters.master_shops.show.master_families.edit' => [
                'masterShop'   => $masterFamily->masterShop->slug,
                'masterFamily' => $masterFamily->slug,
            ],
            'grp.masters.master_shops.show.master_departments.show.master_families.index',
            'grp.masters.master_shops.show.master_departments.show.master_families.show',
            'grp.masters.master_shops.show.master_departments.show.master_families.edit',
            'grp.masters.master_shops.show.master_departments.show.master_families.show.master_products.index',
            'grp.masters.master_shops.show.master_departments.show.master_families.show.master_products.show',
            'grp.masters.master_shops.show.master_departments.show.master_families.show.master_products.edit'
            => [
                'masterShop'       => $masterFamily->masterShop->slug,
                'masterDepartment' => $masterFamily->masterDepartment->slug,
                'masterFamily'     => $masterFamily->slug,
            ],


            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.master_products.index',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.master_products.show',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.master_families.master_products.edit'

            => [
                'masterShop'          => $masterFamily->masterShop->slug,
                'masterDepartment'    => $masterFamily->masterDepartment->slug,
                'masterSubDepartment' => $masterFamily->masterSubDepartment->slug,
                'masterFamily'        => $masterFamily->slug,
            ],
            'grp.masters.master_shops.show.master_sub_departments.master_families.show',
            'grp.masters.master_shops.show.master_sub_departments.master_families.master_products.index' => [
                'masterShop'          => $masterFamily->masterShop->slug,
                'masterSubDepartment' => $masterFamily->masterSubDepartment->slug,
                'masterFamily'        => $masterFamily->slug,
            ],
            default => request()->route()->originalParameters(),
        };
    }
}
