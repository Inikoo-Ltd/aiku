<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 14:48:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Web\WebBlock\GetBanner;
use App\Actions\Web\WebBlock\GetWebBlockDepartments;
use App\Actions\Web\WebBlock\GetWebBlockFamilies;
use App\Actions\Web\WebBlock\GetWebBlockProduct;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;

trait WithGetWebpageWebBlocks
{
    public function getWebBlocks(Webpage $webpage, array $webBlocks): array
    {

        $parsedWebBlocks = [];

        foreach ($webBlocks as $key => $webBlock) {
            $webBlockType = Arr::get($webBlock, 'type');

            if ($webBlockType === 'banner') {
                $parsedWebBlocks[$key] = GetBanner::run($webBlock);
            } elseif (in_array($webBlockType, ['departments'])) {
                $parsedWebBlocks[$key] = GetWebBlockDepartments::run($webpage, $webBlock);
            } elseif (in_array($webBlockType, ['department','department-1'])) {
                $parsedWebBlocks[$key] = GetWebBlockFamilies::run($webpage, $webBlock);
            } elseif (in_array($webBlockType, ['product','product-1'])) {
                $parsedWebBlocks[$key] = GetWebBlockProduct::run($webpage, $webBlock);
            }elseif (in_array($webBlockType, ['families-1'])) {
                $parsedWebBlocks[$key] = GetWebBlockProduct::run($webpage, $webBlock);
            } else {
                $parsedWebBlocks[$key] = $webBlock;
            }
        }

        return $parsedWebBlocks;

    }
}
