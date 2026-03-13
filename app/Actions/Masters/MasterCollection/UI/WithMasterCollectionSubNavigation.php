<?php

/*
 * author Louis Perez
 * created on 10-03-2026-14h-36m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterCollection\UI;

use App\Models\Masters\MasterCollection;

trait WithMasterCollectionSubNavigation
{
    protected function getMasterCollectionSubNavigation(MasterCollection $masterCollection): array
    {
        $currentRoute = request()->route()->getName();

        $baseRoute = match($currentRoute) {

            'grp.masters.master_departments.show.master_collections.collections',
            'grp.masters.master_departments.show.master_collections.linked_master_collections',
            'grp.masters.master_departments.show.master_collections.families',
            'grp.masters.master_departments.show.master_collections.products',
            'grp.masters.master_departments.show.master_collections.show'   =>
                'grp.masters.master_departments.show.master_collections.show',

            'grp.masters.master_shops.show.master_departments.show.master_collections.collections',
            'grp.masters.master_shops.show.master_departments.show.master_collections.linked_master_collections',
            'grp.masters.master_shops.show.master_departments.show.master_collections.families',
            'grp.masters.master_shops.show.master_departments.show.master_collections.products',
            'grp.masters.master_shops.show.master_departments.show.master_collections.show' =>
                'grp.masters.master_shops.show.master_departments.show.master_collections.show',

            'grp.masters.master_shops.show.master_sub_departments.master_collections.collections',
            'grp.masters.master_shops.show.master_sub_departments.master_collections.linked_master_collections',
            'grp.masters.master_shops.show.master_sub_departments.master_collections.families',
            'grp.masters.master_shops.show.master_sub_departments.master_collections.products',
            'grp.masters.master_shops.show.master_sub_departments.master_collections.show'  =>
                'grp.masters.master_shops.show.master_sub_departments.master_collections.show',

            default =>
                'grp.masters.master_shops.show.master_collections.show'
        };


        return [
            [
                'isAnchor' => true,
                'label'    => __('Master Collections'),
                'route'    => [
                    'name'       => $baseRoute,
                    'parameters' => request()->route()->originalParameters()
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Master Collection')
                ]
            ],
            [
                'label'    => __('Collections in Shop'),
                'number'   => $masterCollection->stats->number_collections,
                'route'    => [
                    'name'       => preg_replace('/show$/', 'collections', $baseRoute),
                    'parameters' => request()->route()->originalParameters()
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-store'],
                    'tooltip' => __('Collections in Shop')
                ]
            ],
            [
                'label'    => __('Linked Master Collection'),
                'number'   => $masterCollection->stats->number_current_collection,
                'route'    => [
                    'name'       => preg_replace('/show$/', 'linked_master_collections', $baseRoute),
                    'parameters' => request()->route()->originalParameters()
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-album-collection'],
                    'tooltip' => __('Linked Master Collections')
                ]
            ],
            [
                'label'    => __('Master Families'),
                'number'   => $masterCollection->stats->number_current_families,
                'route'    => [
                    'name'       => preg_replace('/show$/', 'families', $baseRoute),
                    'parameters' => request()->route()->originalParameters()
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-folder'],
                    'tooltip' => __('Master Families')
                ]
            ],
            [
                'label'    => __('Master Products'),
                'number'   => $masterCollection->stats->number_current_products,
                'route'    => [
                    'name'       => preg_replace('/show$/', 'products', $baseRoute),
                    'parameters' => request()->route()->originalParameters()
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-cube'],
                    'tooltip' => __('Master Products')
                ]
            ],
        ];
    }
}
