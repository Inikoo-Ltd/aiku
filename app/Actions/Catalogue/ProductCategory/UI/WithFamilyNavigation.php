<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 21 Dec 2025 11:51:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Traits\Actions\WithNavigation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;

trait WithFamilyNavigation
{
    use WithNavigation;

    protected function getNavigationComparisonColumn(): string
    {
        return 'code';
    }

    protected function applyNavigationFilters(Builder $query, Model $model, ActionRequest $request): void
    {
        /** @var ProductCategory $model */
        $query->where('shop_id', $model->shop_id)->where('type', ProductCategoryTypeEnum::FAMILY);

        $routeName = $request->route()->getName();
        switch ($routeName) {
            case 'grp.org.shops.show.catalogue.departments.show.families.show':
            case 'grp.org.shops.show.catalogue.departments.show.families.edit':
                $query->where('department_id', $model->department_id);
                break;
            case 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show':
            case 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.edit':
            case 'grp.org.shops.show.catalogue.sub_departments.show.families.show':
            case 'grp.org.shops.show.catalogue.sub_departments.show.families.edit':

                $query->where('sub_department_id', $model->sub_department_id);
                break;
        }
    }

    protected function getNavigationLabel(Model $model): string
    {
        /** @var ProductCategory $model */
        return $model->code;
    }

    protected function getNavigationRouteParameters(Model $model, string $routeName): array
    {
        /** @var ProductCategory $family */
        $family = $model;

        return match ($routeName) {
            'grp.org.shops.show.catalogue.departments.show.families.index',
            'grp.org.shops.show.catalogue.departments.show.families.show',
            'grp.org.shops.show.catalogue.departments.show.families.edit' => [
                'organisation' => $family->organisation->slug,
                'shop'         => $family->shop->slug,
                'department'   => $family->department->slug,
                'family'       => $family->slug,
            ],
            'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.index',
            'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show',
            'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.edit' => [
                'organisation'  => $family->organisation->slug,
                'shop'          => $family->shop->slug,
                'department'    => $family->department->slug,
                'subDepartment' => $family->subDepartment->slug,
                'family'        => $family->slug,
            ],
            'grp.org.shops.show.catalogue.sub_departments.show.families.show.products.index',
            'grp.org.shops.show.catalogue.sub_departments.show.families.show',
            'grp.org.shops.show.catalogue.sub_departments.show.families.edit' => [
                'organisation'  => $family->organisation->slug,
                'shop'          => $family->shop->slug,
                'subDepartment' => $family->subDepartment->slug,
                'family'        => $family->slug,
            ],
            default => [
                'organisation' => $family->organisation->slug,
                'shop'         => $family->shop->slug,
                'family'       => $family->slug,
            ],
        };
    }
}
