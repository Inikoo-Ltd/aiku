<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 01:37:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\ProductCategory;

use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum ProductCategoryStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case DISCONTINUING = 'discontinuing';
    case DISCONTINUED = 'discontinued';

    public static function labels($forElements = false): array
    {
        return [
            'in_process'    => __('In Process'),
            'active'        => __('Active'),
            'inactive'      => __('Inactive'),
            'discontinuing' => __('Discontinuing'),
            'discontinued'  => __('Discontinued'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process'    => [
                'tooltip' => __('In process'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-lime-500',
                'color'   => 'lime',
                'app'     => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5'
                ]
            ],
            'active'        => [
                'tooltip' => __('Active'),
                'icon'    => 'fal fa-check',
                'class'   => 'text-emerald-500',
                'color'   => 'emerald',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'inactive'      => [
                'tooltip' => __('Inactive'),
                'icon'    => 'fal fa-ban',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'ban',
                    'type' => 'font-awesome-5'
                ]
            ],
            'discontinuing' => [
                'tooltip' => __('Discontinuing'),
                'icon'    => 'fal fa-exclamation-triangle',
                'class'   => 'text-orange-500',
                'color'   => 'orange',
                'app'     => [
                    'name' => 'exclamation-triangle',
                    'type' => 'font-awesome-5'
                ]
            ],
            'discontinued'  => [
                'tooltip' => __('Discontinued'),
                'icon'    => 'fal fa-times',
                'class'   => 'text-red-500',
                'color'   => 'red',
                'app'     => [
                    'name' => 'times',
                ]
            ],
        ];
    }

    public static function countDepartments(Group|Shop|Organisation|Collection $parent): array
    {
        if ($parent instanceof Organisation) {
            $stats = $parent->catalogueStats;
        } else {
            $stats = $parent->stats;
        }

        return [
            'in_process'    => $stats->number_departments_state_in_process,
            'active'        => $stats->number_departments_state_active,
            'inactive'      => $stats->number_departments_state_inactive,
            'discontinuing' => $stats->number_departments_state_discontinuing,
            'discontinued'  => $stats->number_departments_state_discontinued,
        ];
    }

    public static function countFamily(Group|Shop|ProductCategory|Organisation|Collection $parent): array
    {
        if ($parent instanceof Organisation) {
            $stats = $parent->catalogueStats;
        } else {
            $stats = $parent->stats;
        }

        return [
            'in_process'    => $stats->number_families_state_in_process,
            'active'        => $stats->number_families_state_active,
            'inactive'      => $stats->number_families_state_inactive,
            'discontinuing' => $stats->number_families_state_discontinuing,
            'discontinued'  => $stats->number_families_state_discontinued,
        ];
    }

    public static function countSubDepartment(ProductCategory $parent): array
    {
        $stats = $parent->stats;

        return [
            'in_process'    => $stats->number_sub_departments_state_in_process,
            'active'        => $stats->number_sub_departments_state_active,
            'inactive'      => $stats->number_sub_departments_state_inactive,
            'discontinuing' => $stats->number_sub_departments_state_discontinuing,
            'discontinued'  => $stats->number_sub_departments_state_discontinued,
        ];
    }
}
