<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 14:48:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Models\Web\WebBlock;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

trait WithIrisGetWebpageWebBlocks
{
    use WithFillIrisWebBlocks;

    public function getIrisWebBlocks(Webpage $webpage, array $webBlocks, bool $isLoggedIn): array
    {
        if ($isLoggedIn) {
            return $this->getParsedWebBlocks($webpage, $webBlocks, isLoggedIn: true);
        }

        $ttlSeconds = 60;

        $key = 'iris-web-blocks-website-'.$webpage->website_id.'-webpage-'.$webpage->id;


        return Cache::remember($key, $ttlSeconds, function () use ($webpage, $webBlocks) {
            return $this->getParsedWebBlocks($webpage, $webBlocks, isLoggedIn: false);
        });
    }


    public function getParsedWebBlocks(Webpage $webpage, array $webBlocks, bool $isLoggedIn)
    {
        $parsedWebBlocks = [];

        /** @var WebBlock $webBlock */
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

            $parsedWebBlocks = $this->fillWebBlock($webpage, $parsedWebBlocks, $key, $webBlock, $isLoggedIn);
        }

        return $parsedWebBlocks;
    }

}
