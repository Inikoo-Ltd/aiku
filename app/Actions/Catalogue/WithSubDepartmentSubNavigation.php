<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Jun 2024 14:05:15 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue;

use App\Models\Catalogue\ProductCategory;

trait WithSubDepartmentSubNavigation
{
    protected function getSubDepartmentSubNavigation(ProductCategory $subDepartment): array
    {
        return [
            [
                'isAnchor'   => true,
                'label'    => __('Sub-department'),
                'route'     => [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show.sub-departments.show',
                    'parameters' => [$this->organisation->slug, $subDepartment->shop->slug, $subDepartment->parent->slug, $subDepartment->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-stream'],
                    'tooltip' => __('Sub-department')
                ]
            ],
            [
                'label'    => __('Families'),
                'number'   => $subDepartment->stats->number_families,
                'route'     => [
                    'name'       => 'grp.org.shops.show.catalogue.departments.show.sub-departments.show.family.index',
                    'parameters' => [$this->organisation->slug, $this->shop->slug, $subDepartment->department->slug, $subDepartment->slug]
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-folder'],
                    'tooltip' => __('families')
                ]
            ],
        ];
    }

}
