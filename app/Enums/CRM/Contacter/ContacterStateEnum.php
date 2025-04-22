<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 04-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Enums\CRM\Contacter;

use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;

enum ContacterStateEnum: string
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
            'bounced'      => __('Bounced'),
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

    // public static function count(Organisation|Shop $parent): array
    // {
    //     $stats = $parent->crmStats;

    //     return [
    //         'no-contacted' => $stats->number_prospects_state_no_contacted,
    //         'contacted'    => $stats->number_prospects_state_contacted,
    //         'fail'         => $stats->number_prospects_state_fail,
    //         'success'      => $stats->number_prospects_state_success,
    //     ];
    // }

}
