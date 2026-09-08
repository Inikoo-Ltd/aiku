<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 11:28:50 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Production\Artefact;

use App\Enums\EnumHelperTrait;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactDepartment;
use App\Models\Production\ArtefactFamily;
use App\Models\Production\Production;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum ArtefactStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS        = 'in_process';
    case ACTIVE            = 'active';
    case DORMANT           = 'dormant';
    case DISCONTINUED      = 'discontinued';

    public static function labels(): array
    {
        return [
            'in_process'    => __('In process'),
            'active'        => __('Active'),
            'dormant'       => __('Dormant'),
            'discontinued'  => __('Discontinued'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process' => [
                'tooltip' => __('in process'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-indigo-500'
            ],
            'active'    => [
                'tooltip' => __('Active'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-green-500'
            ],
            'dormant'   => [
                'tooltip' => __('Dormant, nothing sold in 3 years'),
                'icon'    => 'fal fa-moon',
                'class'   => 'text-gray-400'
            ],
            'discontinued'      => [
                'tooltip' => __('discontinued'),
                'icon'    => 'fal fa-laugh',
                'class'   => 'text-red-500'
            ],
        ];
    }

    public static function count(Group|Organisation|Production|ArtefactDepartment|ArtefactFamily $parent): array
    {
        $column = match (true) {
            $parent instanceof Group          => 'group_id',
            $parent instanceof Organisation   => 'organisation_id',
            $parent instanceof ArtefactDepartment => 'artefact_department_id',
            $parent instanceof ArtefactFamily => 'artefact_family_id',
            default                           => 'production_id',
        };
        $counts = Artefact::where($column, $parent->id)->groupBy('state')->selectRaw('state, count(*) as n')->pluck('n', 'state');

        return array_map(fn ($state) => (int) ($counts[$state] ?? 0), array_combine(self::values(), self::values()));
    }

}
