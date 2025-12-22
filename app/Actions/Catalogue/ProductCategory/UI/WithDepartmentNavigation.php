<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 21 Dec 2025 11:40:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Traits\Actions\WithNavigation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Database\Eloquent\Builder;

trait WithDepartmentNavigation
{
    use WithNavigation;

    protected function getNavigationComparisonColumn(): string
    {
        return 'code';
    }

    protected function applyNavigationFilters(Builder $query, Model $model, ActionRequest $request): void
    {
        /** @var ProductCategory $model */
        $query->where('shop_id', $model->shop_id)->where('type', ProductCategoryTypeEnum::DEPARTMENT);
    }

    protected function getNavigationLabel(Model $model): string
    {
        /** @var ProductCategory $model */
        return $model->code;
    }

    protected function getNavigationRouteParameters(Model $model, string $routeName): array
    {
        /** @var ProductCategory $department */
        $department = $model;

        return [
            'organisation' => $department->organisation->slug,
            'shop'         => $department->shop->slug,
            'department'   => $department->slug
        ];
    }

}
