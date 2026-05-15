<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 May 2026 12:03:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Web\Crawl;

use App\Enums\EnumHelperTrait;

enum CrawlTypeEnum: string
{
    use EnumHelperTrait;

    case HTML = 'html';
    case JAVASCRIPT = 'javascript';


}
