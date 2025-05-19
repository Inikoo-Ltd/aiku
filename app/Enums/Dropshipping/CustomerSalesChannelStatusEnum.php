<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jan 2024 15:25:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Dropshipping;

use App\Enums\EnumHelperTrait;

enum CustomerSalesChannelStatusEnum: string
{
    use EnumHelperTrait;

    case OPEN = 'open';
    case CLOSED = 'closed';


    public static function labels(): array
    {
        return [
            'open'   => __('Open'),
            'closed' => __('Closed'),
        ];
    }


}
