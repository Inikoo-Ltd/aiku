<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 21 Dec 2025 21:47:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection\UI;

use App\Actions\Traits\Actions\WithNavigation;
use App\Models\Masters\MasterCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;

trait WithMasterCollectionNavigation
{
    use WithNavigation;

    protected function getNavigationComparisonColumn(): string
    {
        return 'code';
    }

    protected function applyNavigationFilters(Builder $query, Model $model, ActionRequest $request): void
    {
        /** @var MasterCollection $model */
        $query->where('master_shop_id', $model->master_shop_id);

        $routeName = $request->route()->getName();

        if (
            in_array(
                $routeName,
                [
                    'grp.masters.master_shops.show.master_departments.show.master_collections.index',
                    'grp.masters.master_shops.show.master_departments.show.master_collections.show',
                    'grp.masters.master_shops.show.master_sub_departments.master_collections.index',
                    'grp.masters.master_shops.show.master_sub_departments.master_collections.show'

                ]
            )) {
            $query->join('model_has_master_collections', function ($join) {
                $join->on('model_has_master_collections.master_collection_id', '=', 'master_collections.id');
            });
            $query->where('model_has_master_collections.model_type', 'MasterProductCategory');
            $query->where('model_has_master_collections.model_id', $this->parent->id);
        }
    }

    protected function getNavigationLabel(Model $model): string
    {
        /** @var MasterCollection $model */
        return $model->name;
    }

    protected function getNavigationRouteParameters(Model $model, string $routeName): array
    {
        /** @var MasterCollection $masterCollection */
        $masterCollection = $model;

        $routeParameters = request()->route()->parameters();

        return match ($routeName) {
            'grp.masters.master_shops.show.master_collections.show' => [
                'masterShop'       => $masterCollection->masterShop->slug,
                'masterCollection' => $masterCollection->slug,
            ],
            'grp.masters.master_shops.show.master_departments.show.master_collections.index',
            'grp.masters.master_shops.show.master_departments.show.master_collections.show' => [
                'masterShop'       => $masterCollection->masterShop->slug,
                'masterDepartment' => $routeParameters['masterDepartment']->slug,
                'masterCollection' => $masterCollection->slug,
            ],
            'grp.masters.master_shops.show.master_sub_departments.master_collections.index',
            'grp.masters.master_shops.show.master_sub_departments.master_collections.show' => [
                'masterShop'          => $masterCollection->masterShop->slug,
                'masterSubDepartment' => $routeParameters['masterSubDepartment']->slug,
                'masterCollection'    => $masterCollection->slug,
            ],
            default => request()->route()->originalParameters(),
        };
    }
}
