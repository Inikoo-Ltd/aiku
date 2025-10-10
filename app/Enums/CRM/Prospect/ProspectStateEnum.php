<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Jun 2023 08:44:31 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Prospect;

use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;

enum ProspectStateEnum: string
{
    use EnumHelperTrait;

    case NO_CONTACTED = 'no-contacted';
    case CONTACTED    = 'contacted';
    case FAIL         = 'fail';
    case SUCCESS      = 'success';


    public static function labels(): array
    {
        return [
            'no-contacted' => __('No contacted'),
            'contacted'    => __('Contacted'),
            'fail'         => __('Fail'),
            'success'      => __('Success'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'no-contacted' => [
                'tooltip' => __('no contacted'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-indigo-500'


            ],
            'contacted'    => [

                'tooltip' => __('contacted'),
                'icon'    => 'fal fa-chair',
            ],
            'fail'         => [

                'tooltip' => __('fail'),
                'icon'    => 'fal fa-thumbs-down',
                'class'   => 'text-red'

            ],
            'success'      => [

                'tooltip' => __('success'),
                'icon'    => 'fal fa-laugh'

            ],

        ];
    }

    public static function count(Organisation|Shop $parent): array
    {
        $stats = $parent->crmStats;

        return [
            'no-contacted' => $stats->number_prospects_state_no_contacted,
            'contacted'    => $stats->number_prospects_state_contacted,
            'fail'         => $stats->number_prospects_state_fail,
            'success'      => $stats->number_prospects_state_success,
        ];
    }

}
