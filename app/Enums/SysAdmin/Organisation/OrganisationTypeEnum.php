<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jan 2024 12:36:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\SysAdmin\Organisation;

use App\Enums\EnumHelperTrait;

enum OrganisationTypeEnum: string
{
    use EnumHelperTrait;

    case SHOP = 'shop';
    case AGENT = 'agent';
    case DIGITAL_AGENCY = 'digital_agency';

    public static function labels(): array
    {
        return [
            'shop' => __('Shop'),
            'agent' => __('Agent'),
            'digital_agency' => __('Digital Agency'),
        ];
    }

    public static function typeIcon(): array
    {
        return [
            'shop' => [
                'tooltip' => __('Shop'),
                'icon' => 'fal fa-store',

            ],
            'agent' => [
                'tooltip' => __('Agent'),
                'icon' => 'fal fa-people-arrows',

            ],
            'digital_agency' => [
                'tooltip' => __('Digital Agency'),
                'icon' => 'fal fa-ad',
            ],
        ];
    }
}
