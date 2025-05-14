<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 14:48:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Web\WebBlock\GetBanner;
use Illuminate\Support\Arr;

trait WithGetWebpageWebBlocks
{
    public function getWebBlocks(array $webBlocks): array
    {

        foreach ($webBlocks as $key => $webBlock) {
            if (Arr::get($webBlock, 'type') === 'banner') {
                $webBlocks[$key] = GetBanner::run($webBlock);
            }
        }

        return $webBlocks;

    }
}
