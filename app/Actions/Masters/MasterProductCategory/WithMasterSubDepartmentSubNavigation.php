<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory;

use App\Models\Masters\MasterProductCategory;

trait WithMasterSubDepartmentSubNavigation
{
    protected function getMasterSubDepartmentSubNavigation(MasterProductCategory $masterSubDepartment): array
    {
        return [
            [
                'isAnchor'   => true,
                'label'    => __('Sub-department'),
                'route'     => [
                    'name'       => 'grp.masters.master_departments.show.master_sub_departments.show',
                    'parameters' => [$masterSubDepartment->parent->slug,$masterSubDepartment->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Sub-department')
                ]
            ],
            // [
            //     'label'    => __('Families'),
            //     'number'   => $masterSubDepartment->stats->number_families,
            //     'route'     => [
            //         'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.index',
            //         'parameters' => [$this->organisation->slug, $this->shop->slug, $masterSubDepartment->department->slug, $masterSubDepartment->slug]
            //     ],
            //     'leftIcon' => [
            //         'icon'    => ['fal', 'fa-folder'],
            //         'tooltip' => __('families')
            //     ]
            // ],
            // [
            //     'label'    => __('Collections'),
            //     'number'   => 0,
            //     'route'     => [
            //         'name'       => 'grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.index',
            //         'parameters' => [$this->organisation->slug, $this->shop->slug, $masterSubDepartment->department->slug, $masterSubDepartment->slug]
            //     ],
            //     'leftIcon' => [
            //         'icon'    => ['fal', 'fa-album-collection'],
            //         'tooltip' => __('collections')
            //     ]
            // ],
        ];
    }

}
