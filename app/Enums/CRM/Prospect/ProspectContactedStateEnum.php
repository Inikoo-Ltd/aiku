<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Nov 2023 11:56:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Prospect;

use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;

enum ProspectContactedStateEnum: string
{
    use EnumHelperTrait;

    case NA = 'no_applicable';
    case SOFT_BOUNCED = 'soft_bounced';
    case NEVER_OPEN   = 'never_open';
    case OPEN         = 'open';
    case CLICKED      = 'clicked';

    public static function labels(): array
    {
        return [
            'no_applicable' => __('NA'),
            'soft_bounced'  => __('Bounced'),
            'never_open'    => __('Never open'),
            'open'          => __('Open'),
            'clicked'       => __('Clicked'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'no_applicable' => [
                'tooltip' => __('NA'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-indigo-500'


            ],
            'soft_bounced'  => [

                'tooltip' => __('Bounced'),
                'icon'    => 'fal fa-dungeon',

            ],
            'never_open'    => [

                'tooltip' => __('Never open'),
                'icon'    => 'fal fa-eye-slash',
                'class'   => 'text-red'

            ],
            'open'          => [
                'tooltip' => __('open'),
                'icon'    => 'fal fa-eye'
            ],
            'clicked'       => [
                'tooltip' => __('clicked'),
                'icon'    => 'fal fa-mouse-pointer'
            ],
        ];
    }

    public static function count(Organisation|Shop $parent): array
    {
        $stats = $parent->crmStats;

        return [
            'no_applicable' => $stats->number_prospects_contacted_state_no_applicable,
            'soft_bounced'  => $stats->number_prospects_contacted_state_soft_bounced,
            'never_open'    => $stats->number_prospects_contacted_state_never_open,
            'open'          => $stats->number_prospects_contacted_state_open,
            'clicked'       => $stats->number_prospects_contacted_state_clicked
        ];
    }

}
