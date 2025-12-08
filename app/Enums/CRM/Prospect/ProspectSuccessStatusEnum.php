<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Nov 2023 11:40:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Prospect;

use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;

enum ProspectSuccessStatusEnum: string
{
    use EnumHelperTrait;

    case NA = 'no_applicable';
    case REGISTERED = 'registered';
    case INVOICED = 'invoiced';

    public static function labels(): array
    {
        return [
            'no_applicable' => __('N/A'),
            'registered' => __('Registered'),
            'invoiced' => __('Invoiced'),
        ];
    }

    public static function statusIcon(): array
    {
        return [
            'no_applicable' => [

                'tooltip' => __('N/A'),
                'icon' => 'fal fa-location-slash',
            ],
            'registered' => [

                'tooltip' => __('registered'),
                'icon' => 'fal fa-sign-in',

            ],
            'invoiced' => [

                'tooltip' => __('invoiced'),
                'icon' => 'fal fa-file-invoice',

            ],
        ];
    }

    public static function count(Organisation|Shop $parent): array
    {
        $stats = $parent->crmStats;

        return [
            'no_applicable' => $stats->number_prospects_success_status_no_applicable,
            'registered' => $stats->number_prospects_success_status_registered,
            'invoiced' => $stats->number_prospects_success_status_invoiced,
        ];
    }
}
