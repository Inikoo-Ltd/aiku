<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 01 Jan 2024 22:54:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Web;

use App\Enums\EnumHelperTrait;

enum DnsCloudflareTypeEnum: string
{
    use EnumHelperTrait;

    case MX = 'MX';
    case CNAME = 'CNAME';
    case A = 'A';
    case TXT = 'TXT';
}
