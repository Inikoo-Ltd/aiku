<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 21 Dec 2025 18:15:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\Traits\Actions\WithNavigation;
use App\Models\Catalogue\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\ActionRequest;

trait WithCollectionNavigation
{
    use WithNavigation;

    protected function applyNavigationFilters(Builder $query, Model $model, ActionRequest $request): void
    {
        /** @var Collection $model */
        $query->where('shop_id', $model->shop_id);

        $routeName = $request->route()->getName();

        if (
            in_array(
                $routeName,
                [
                    'grp.org.shops.show.catalogue.departments.show.collections.show',
                    'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.show',
                    'grp.org.shops.show.catalogue.sub_departments.show.collection.show',
                    'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show.collection.show',
                    'grp.org.shops.show.catalogue.families.show.collection.show'
                ]
            )) {
            $query->join('model_has_collections', function ($join) {
                $join->on('model_has_collections.collection_id', '=', 'collections.id');
            });
            $query->where('model_has_collections.model_type', 'ProductCategory');
            $query->where('model_has_collections.model_id', $this->parent->id);
        }
    }

    protected function getNavigationLabel(Model $model): string
    {
        /** @var Collection $model */
        return $model->code.' - '.$model->name;
    }

    protected function getNavigationRouteParameters(Model $model, string $routeName): array
    {
        /** @var Collection $collection */
        $collection = $model;

        return match ($routeName) {
            'shops.org.collections.show' => [
                'collection' => $collection->slug
            ],
            'grp.org.shops.show.catalogue.collections.show' => [
                'organisation' => $this->organisation->slug,
                'shop'         => $collection->shop->slug,
                'collection'   => $collection->slug
            ],
            'grp.org.shops.show.catalogue.departments.show.collections.show' => [
                'organisation' => $this->organisation->slug,
                'shop'         => $collection->shop->slug,
                'department'   => $this->parent->slug,
                'collection'   => $collection->slug
            ],
            'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.show' => [
                'organisation'  => $this->organisation->slug,
                'shop'          => $collection->shop->slug,
                'department'    => $this->parent->department->slug,
                'subDepartment' => $this->parent->slug,
                'collection'    => $collection->slug
            ],
            'grp.org.shops.show.catalogue.sub_departments.show.collection.show' => [
                'organisation'  => $this->organisation->slug,
                'shop'          => $collection->shop->slug,
                'subDepartment' => $this->parent->slug,
                'collection'    => $collection->slug
            ],
            'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show.collection.show' => [
                'organisation'  => $this->organisation->slug,
                'shop'          => $collection->shop->slug,
                'department'    => $this->parent->department->slug,
                'subDepartment' => $this->parent->parent->slug,
                'family'        => $this->parent->slug,
                'collection'    => $collection->slug
            ],
            'grp.org.shops.show.catalogue.families.show.collection.show' => [
                'organisation' => $this->organisation->slug,
                'shop'         => $collection->shop->slug,
                'family'       => $this->parent->slug,
                'collection'   => $collection->slug
            ],
            default => request()->route()->originalParameters(),
        };
    }
}
