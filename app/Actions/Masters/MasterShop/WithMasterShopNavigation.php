<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Dec 2025 23:37:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop;

use App\Actions\Traits\Actions\WithNavigation;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;

trait WithMasterShopNavigation
{
    use WithNavigation;

    protected function getNavigationComparisonColumn(): string
    {
        return 'code';
    }

    protected function applyNavigationFilters(Builder $query, Model $model, ActionRequest $request): void
    {
        /** @var MasterShop $model */
        $query->where('group_id', $model->group_id);
    }

    protected function getNavigationLabel(Model $model): string
    {
        /** @var ProductCategory $model */
        return $model->code.': '.$model->name;
    }

    protected function getNavigationRouteParameters(Model $model, string $routeName): array
    {
        /** @var ProductCategory $model */
        return [
            'masterShop' => $model->slug
        ];
    }
}
