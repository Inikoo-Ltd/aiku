<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 21 Dec 2025 21:49:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Traits\Actions\WithNavigation;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;

trait WithMasterSubDepartmentNavigation
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
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.index',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.show',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.edit'
        ])) {
            $query->where('master_department_id', $model->master_department_id);
        }

    }

    protected function getNavigationLabel(Model $model): string
    {
        /** @var MasterProductCategory $model */
        return $model->name;
    }

    protected function getNavigationRouteParameters(Model $model, string $routeName): array
    {
        /** @var MasterProductCategory $masterSubDepartment */
        $masterSubDepartment = $model;

        return match ($routeName) {
            'grp.masters.master_shops.show.master_sub_departments.index',
            'grp.masters.master_shops.show.master_sub_departments.show',
            'grp.masters.master_shops.show.master_sub_departments.edit' => [
                'masterShop'          => $masterSubDepartment->masterShop->slug,
                'masterSubDepartment' => $masterSubDepartment->slug,
            ],
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.index',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.show',
            'grp.masters.master_shops.show.master_departments.show.master_sub_departments.edit' => [
                'masterShop'          => $masterSubDepartment->masterShop->slug,
                'masterDepartment'    => $masterSubDepartment->masterDepartment->slug,
                'masterSubDepartment' => $masterSubDepartment->slug,
            ],
            default => request()->route()->originalParameters(),
        };
    }
}
