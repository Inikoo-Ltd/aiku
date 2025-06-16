<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 13 Mar 2023 01:37:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Collection;

use App\Enums\EnumHelperTrait;

enum CollectionStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case DISCONTINUING = 'discontinuing';
    case DISCONTINUED = 'discontinued';

    public static function labels(): array
    {
        return [
            'in_process'    => __('In Process'),
            'active'        => __('Active'),
            'inactive'      => __('Inactive'),
            'discontinuing' => __('Discontinuing'),
            'discontinued'  => __('Discontinued'),
        ];
    }


}
