<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 Oct 2025 11:35:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Inikoo Ltd
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;

class RepairShopPlatformsSalesIntervals
{
    public string $commandSignature = 'repair:shop_platforms_sales_intervals ';

    public function asCommand(Command $command): void
    {
        foreach (Shop::whereIn('type', [ShopTypeEnum::DROPSHIPPING, ShopTypeEnum::FULFILMENT]) as $shop) {
            // todo create the PlatofrmSales....
        }
    }

}
