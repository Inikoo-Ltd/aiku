<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 May 2025 14:48:48 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Models\Web\Webpage;

trait WithGetWebpageWebBlocks
{
    use WithFillIrisWebBlocks;
    public function getWebBlocks(Webpage $webpage, array $webBlocks): array
    {
        $parsedWebBlocks = [];

        foreach ($webBlocks as $key => $webBlock) {

            $parsedWebBlocks = $this->fillWebBlock($webpage, $parsedWebBlocks, $key, $webBlock);

        }

        return $parsedWebBlocks;
    }

}
