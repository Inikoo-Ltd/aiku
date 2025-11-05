<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 Oct 2025 11:35:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Inikoo Ltd
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;

class RepairShopPlatformsSalesIntervals extends OrgAction
{
    public string $commandSignature = 'repair:shop_platform_sales_intervals';

    public function asCommand(Command $command): void
    {
        foreach (Shop::where('type', ShopTypeEnum::DROPSHIPPING)->get() as $shop) {
            foreach (Platform::all() as $platform) {
                $shop->platformSalesIntervals()->updateOrCreate(['platform_id' => $platform->id], ['platform_id' => $platform->id]);
            }
        }
    }
}
