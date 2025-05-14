<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 14:48:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Web\WebBlock\GetBanner;
use Illuminate\Support\Arr;

trait WithIrisGetWebpageWebBlocks
{
    public function getIrisWebBlocks(array $webBlocks, bool $isLoggedIn): array
    {

        $parsedWebBlocks = [];
        foreach ($webBlocks as $key => $webBlock) {
            if (!Arr::get($webBlock, 'show')) {
                continue;
            }

            if ($isLoggedIn && !Arr::get($webBlock, 'visibility.in')) {
                continue;
            }

            if (!$isLoggedIn && !Arr::get($webBlock, 'visibility.out')) {
                continue;
            }

            if (Arr::get($webBlock, 'type') === 'banner') {
                $parsedWebBlocks[$key] = GetBanner::run($webBlock);
            } else {
                $parsedWebBlocks[$key] = $webBlock;
            }
        }

        return $parsedWebBlocks;
    }
}
