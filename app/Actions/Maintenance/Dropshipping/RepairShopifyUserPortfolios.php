<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Jul 2025 20:47:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\CustomerSalesChannel\WithExternalPlatforms;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairShopifyUserPortfolios
{
    use AsAction;
    use WithActionUpdate;
    use WithExternalPlatforms;


    public function getCommandSignature(): string
    {
        return 'repair:shopify_portfolios';
    }

    public function asCommand(Command $command): void
    {
        $platform = Platform::where('type', PlatformTypeEnum::SHOPIFY)->firstOrFail();

        /** @var CustomerSalesChannel $customerSalesChannel */
        foreach (CustomerSalesChannel::where('platform_id', $platform->id)
                     ->where('status', CustomerSalesChannelStatusEnum::OPEN)
                     ->get() as $customerSalesChannel) {
            foreach ($customerSalesChannel->portfolios as $portfolio) {
                $command->info($portfolio->name . '-' . $portfolio->id . '\n');
            }
        }
    }

}
