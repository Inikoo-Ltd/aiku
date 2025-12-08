<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Jan 2024 22:42:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Web\Website;

use App\Enums\EnumHelperTrait;

enum WebsiteCloudflareStatusEnum: string
{
    use EnumHelperTrait;

    case NOT_SET = 'not-set';

    case PENDING = 'pending';
    case ACTIVE = 'active';
}
