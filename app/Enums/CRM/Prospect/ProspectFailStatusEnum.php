<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Nov 2023 11:29:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Prospect;

use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;

enum ProspectFailStatusEnum: string
{
    use EnumHelperTrait;

    case NA = 'no_applicable';
    case NOT_INTERESTED = 'not_interested';
    case UNSUBSCRIBED = 'unsubscribed';
    case HARD_BOUNCED = 'hard_bounced';
    case INVALID = 'invalid';

    public static function labels(): array
    {
        return [
            'no_applicable' => __('N/A'),
            'not_interested' => __('Not interested'),
            'unsubscribed' => __('Unsubscribed'),
            'invalid' => __('Invalid'),
            'hard_bounced' => __('Hard Bounced'),
        ];
    }

    public static function statusIcon(): array
    {
        return [
            'no_applicable' => [

                'tooltip' => __('N/A'),
                'icon' => 'fal fa-location-slash',

            ],

            'not_interested' => [

                'tooltip' => __('not interested'),
                'icon' => 'fal fa-snooze',
                'class' => 'text-red',

            ],

            'unsubscribed' => [

                'tooltip' => __('Unsubscribed'),
                'icon' => 'fal fa-unlink',
                'class' => 'text-red-300',
            ],

            'invalid' => [

                'tooltip' => __('invalid'),
                'icon' => 'fal fa-exclamation-triangle',
                'class' => 'text-red-300',
            ],

            'hard_bounced' => [

                'tooltip' => __('Hard Bounced'),
                'icon' => 'fal fa-exclamation-circle',
                'class' => 'text-red-700',
            ],

        ];
    }

    public static function count(Organisation|Shop $parent): array
    {
        $stats = $parent->crmStats;

        return [
            'no_applicable' => $stats->number_prospects_fail_status_no_applicable,
            'not_interested' => $stats->number_prospects_fail_status_not_interested,
            'unsubscribed' => $stats->number_prospects_fail_status_unsubscribed,
            'invalid' => $stats->number_prospects_fail_status_invalid,
            'hard_bounced' => $stats->number_prospects_fail_status_hard_bounced,

        ];
    }
}
