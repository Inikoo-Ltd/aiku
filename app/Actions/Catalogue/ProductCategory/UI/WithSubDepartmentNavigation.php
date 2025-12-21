<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 21 Dec 2025 16:04:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Traits\Actions\WithNavigation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;

trait WithSubDepartmentNavigation
{
    use WithNavigation;

    protected function getNavigationComparisonColumn(): string
    {
        return 'code';
    }

    protected function applyNavigationFilters(Builder $query, Model $model, ActionRequest $request): void
    {
        /** @var ProductCategory $model */
        $query->where('shop_id', $model->shop_id)
            ->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT);

        $routeName = $request->route()->getName();

        if (in_array($routeName, [
            'grp.org.shops.show.catalogue.departments.show.sub_departments.show',
            'grp.org.shops.show.catalogue.departments.show.sub_departments.edit'
        ])) {
            $query->where('department_id', $model->department_id);
        }

    }

    protected function getNavigationLabel(Model $model): string
    {
        /** @var ProductCategory $model */
        return $model->code.' '.$model->name;
    }

    protected function getNavigationRouteParameters(Model $model, string $routeName): array
    {
        /** @var ProductCategory $subDepartment */
        $subDepartment = $model;

        return match ($routeName) {
            'grp.org.shops.show.catalogue.sub_departments.show',
            'grp.org.shops.show.catalogue.sub_departments.edit',
            'grp.org.shops.show.catalogue.sub_departments.show.families.index',
            'grp.org.shops.show.catalogue.sub_departments.show.families.show',
            'grp.org.shops.show.catalogue.sub_departments.show.products.index',
            'grp.org.shops.show.catalogue.sub_departments.show.products.show',
            'grp.org.shops.show.catalogue.sub_departments.show.families.show.products.index',
            'grp.org.shops.show.catalogue.sub_departments.show.collection.index',
            'grp.org.shops.show.catalogue.sub_departments.show.collection.show' => [
                'organisation'  => $subDepartment->organisation->slug,
                'shop'          => $subDepartment->shop->slug,
                'subDepartment' => $subDepartment->slug,
            ],
            default => [
                'organisation'  => $subDepartment->organisation->slug,
                'shop'          => $subDepartment->shop->slug,
                'department'    => $subDepartment->parent->slug,
                'subDepartment' => $subDepartment->slug,
            ],
        };
    }
}
