<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 May 2026 12:03:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Web\Crawl;

use App\Enums\EnumHelperTrait;

enum CrawlTriggerEnum: string
{
    use EnumHelperTrait;

    case DEPLOYMENT = 'deployment';
    case MANUAL_BREAK_CACHE = 'manual_break_cache';
    case WEBSITE_UPDATE = 'website_update';
    case COMMAND = 'command';


}
