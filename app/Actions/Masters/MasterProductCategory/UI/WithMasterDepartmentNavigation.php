<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 21 Dec 2025 21:46:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Actions\Traits\Actions\WithNavigation;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;

trait WithMasterDepartmentNavigation
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
    }

    protected function getNavigationLabel(Model $model): string
    {
        /** @var MasterProductCategory $model */
        return $model->name;
    }

    protected function getNavigationRouteParameters(Model $model, string $routeName): array
    {
        /** @var MasterProductCategory $masterDepartment */
        $masterDepartment = $model;

        return match ($routeName) {
            'grp.masters.master_departments.show' => [
                'masterDepartment' => $masterDepartment->slug,
            ],
            'grp.masters.master_shops.show.master_departments.show' => [
                'masterShop'       => $masterDepartment->masterShop->slug,
                'masterDepartment' => $masterDepartment->slug,
            ],
            default => request()->route()->originalParameters(),
        };
    }
}
