<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 26 Aug 2022 01:35:48 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\Catalogue\Shop;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;

class StoreShopPlatformStats extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop): Shop
    {
        $platforms = $shop->group->platforms;

        foreach ($platforms as $platform) {
            $shop->platformStats()->firstOrCreate([
                'platform_id' => $platform->id,
            ]);
        }


        return $shop;
    }
}
